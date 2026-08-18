<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatrolCaseReportResource;
use App\Http\Resources\PatrolEntryResource;
use App\Jobs\ReverseGeocodeLocation;
use App\Models\Beats;
use App\Models\PatrolCaseMedia;
use App\Models\PatrolCaseReports;
use App\Models\PatrolEntryVehicles;
use App\Models\PatrolRoutePoints;
use App\Models\PatrollingEntries;
use App\Models\PatrolTypes;
use App\Models\Ranges;
use App\Models\Vehicles;
use App\Services\PatrolPhotoService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PatrolEntryController extends Controller
{
    public function __construct(
        private readonly PatrolPhotoService $photos,
    ) {}

    /**
     * Entries created by the currently authenticated field user.
     */
    public function index(Request $request)
    {
        $entries = PatrollingEntries::query()
            ->where('pe_patrol_leader_id', $request->user()->u_id)
            ->with(['range', 'beat', 'patrolType', 'modes', 'vehicles.vehicle'])
            ->latest('pe_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Patrol entries retrieved successfully.',
            'data' => PatrolEntryResource::collection($entries),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
                'last_page' => $entries->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, PatrollingEntries $entry)
    {
        $this->authorizeOwner($request, $entry);

        $entry->load(['range', 'beat', 'patrolType', 'modes', 'vehicles.vehicle', 'deployedStaff', 'caseReports.media', 'routePoints']);

        return response()->json([
            'success' => true,
            'message' => 'Patrol entry retrieved successfully.',
            'data' => new PatrolEntryResource($entry),
        ]);
    }

    /**
     * Create a new patrolling entry.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'pe_patrol_date' => ['required', 'date', 'before_or_equal:today'],
            'pe_start_time' => ['required', 'date_format:H:i'],
            'pe_range_id' => ['required', 'uuid', 'exists:ranges,rn_id'],
            'pe_beat_id' => ['nullable', 'uuid', 'exists:beats,bt_id'],
            'pe_area_covered' => ['nullable', 'string', 'max:255'],
            'pe_patrol_type_id' => ['required', 'uuid', 'exists:patrol_types,pt_id'],
            'pe_staff_deployed_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'staff_ids' => ['nullable', 'array', 'max:1000'],
            'staff_ids.*' => ['uuid', 'distinct', 'exists:users,u_id'],
            'pe_gps_enabled' => ['required', 'boolean'],
            'pe_start_latitude' => ['required_if:pe_gps_enabled,true', 'nullable', 'numeric', 'between:-90,90'],
            'pe_start_longitude' => ['required_if:pe_gps_enabled,true', 'nullable', 'numeric', 'between:-180,180'],
            'mode_ids' => ['required', 'array', 'min:1'],
            'mode_ids.*' => ['uuid', 'distinct', 'exists:patrolling_modes,pm_id'],
            'vehicles' => ['sometimes', 'array'],
            'vehicles.*.type' => ['required_with:vehicles', Rule::in(['4_wheeler', 'boat'])],
            'vehicles.*.registration_no' => ['required_with:vehicles', 'string', 'max:50'],
            'vehicles.*.start_odometer' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'pe_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $user->ranges()->where('rn_id', $validated['pe_range_id'])->exists()) {
            throw ValidationException::withMessages([
                'pe_range_id' => 'You do not have access to this range.',
            ]);
        }

        if (isset($validated['staff_ids']) && count($validated['staff_ids']) > $validated['pe_staff_deployed_count']) {
            throw ValidationException::withMessages([
                'staff_ids' => 'Number of named staff cannot exceed the staff deployed count.',
            ]);
        }

        $range = Ranges::findOrFail($validated['pe_range_id']);

        $patrolType = PatrolTypes::findOrFail($validated['pe_patrol_type_id']);

        if (! $patrolType->isAllowedForCategory($range->rn_category)) {
            throw ValidationException::withMessages([
                'pe_patrol_type_id' => "The \"{$patrolType->pt_name}\" patrol type is not available for this range.",
            ]);
        }

        if (! empty($validated['pe_beat_id'])) {
            $beat = Beats::findOrFail($validated['pe_beat_id']);

            if ($beat->bt_range_id !== $range->rn_id) {
                throw ValidationException::withMessages([
                    'pe_beat_id' => 'The selected beat does not belong to the selected range.',
                ]);
            }
        }

        $modeNames = DB::table('patrolling_modes')
            ->whereIn('pm_id', $validated['mode_ids'])
            ->pluck('pm_mode_name')
            ->map(fn ($name) => strtolower($name));

        $vehiclesInput = $validated['vehicles'] ?? [];
        $has4Wheeler = collect($vehiclesInput)->contains(fn ($v) => $v['type'] === '4_wheeler');
        $hasBoat = collect($vehiclesInput)->contains(fn ($v) => $v['type'] === 'boat');

        if ($modeNames->contains(fn ($n) => str_contains($n, 'wheel') || str_contains($n, 'vehicle')) && ! $has4Wheeler) {
            throw ValidationException::withMessages([
                'vehicles' => 'At least one 4-wheeler vehicle is required for the selected patrol mode.',
            ]);
        }

        if ($modeNames->contains(fn ($n) => str_contains($n, 'boat')) && ! $hasBoat) {
            throw ValidationException::withMessages([
                'vehicles' => 'At least one boat is required for the selected patrol mode.',
            ]);
        }

        $entry = DB::transaction(function () use ($validated, $user, $range, $vehiclesInput) {
            $entry = PatrollingEntries::create([
                'pe_patrol_id' => $this->generatePatrolId($range, $user, $validated['pe_patrol_date']),
                'pe_patrol_date' => $validated['pe_patrol_date'],
                'pe_start_time' => $validated['pe_start_time'],
                'pe_range_id' => $range->rn_id,
                'pe_beat_id' => $validated['pe_beat_id'] ?? null,
                'pe_area_covered' => $validated['pe_area_covered'] ?? null,
                'pe_patrol_type_id' => $validated['pe_patrol_type_id'],
                'pe_staff_deployed_count' => $validated['pe_staff_deployed_count'],
                'pe_patrol_leader_id' => $user->u_id,
                'pe_gps_enabled' => $validated['pe_gps_enabled'],
                'pe_start_latitude' => $validated['pe_start_latitude'] ?? null,
                'pe_start_longitude' => $validated['pe_start_longitude'] ?? null,
                'pe_status' => PatrollingEntries::STATUS_IN_PROGRESS,
                'pe_remarks' => $validated['pe_remarks'] ?? null,
            ]);

            $entry->modes()->sync($validated['mode_ids']);

            if (! empty($validated['staff_ids'])) {
                $entry->deployedStaff()->sync($validated['staff_ids']);
            }

            foreach ($vehiclesInput as $vehicleInput) {
                $vehicle = Vehicles::updateOrCreate(
                    [
                        'vh_range_id' => $range->rn_id,
                        'vh_registration_number' => strtoupper(trim($vehicleInput['registration_no'])),
                    ],
                    [
                        'vh_type' => $vehicleInput['type'] === '4_wheeler' ? 'vehicle' : 'boat',
                    ]
                );

                PatrolEntryVehicles::create([
                    'pev_entry_id' => $entry->pe_id,
                    'pev_vehicle_id' => $vehicle->vh_id,
                    'pev_vehicle_type' => $vehicleInput['type'],
                    'pev_start_odometer' => $vehicleInput['start_odometer'] ?? null,
                ]);
            }

            return $entry;
        });

        if (! empty($validated['pe_gps_enabled']) && ! empty($validated['pe_start_latitude'])) {
            ReverseGeocodeLocation::dispatch(
                PatrollingEntries::class,
                $entry->pe_id,
                'pe_id',
                (float) $validated['pe_start_latitude'],
                (float) $validated['pe_start_longitude'],
                'pe_start_address'
            );
        }

        $entry->load(['range', 'beat', 'patrolType', 'modes', 'vehicles.vehicle', 'deployedStaff']);

        return response()->json([
            'success' => true,
            'message' => 'Patrol entry created successfully.',
            'data' => new PatrolEntryResource($entry),
        ], 201);
    }

    /**
     * Record a live GPS ping while the patrol is in progress (client polls this ~every 30s).
     */
    public function addGpsPing(Request $request, PatrollingEntries $entry)
    {
        $this->authorizeOwner($request, $entry);
        $this->assertInProgress($entry);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $point = PatrolRoutePoints::create([
            'prp_entry_id' => $entry->pe_id,
            'prp_latitude' => $validated['latitude'],
            'prp_longitude' => $validated['longitude'],
            'prp_recorded_at' => $validated['recorded_at'] ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'GPS point recorded.',
            'data' => [
                'id' => $point->prp_id,
                'recorded_at' => $point->prp_recorded_at->toISOString(),
            ],
        ], 201);
    }

    /**
     * Report a case/incident found during the patrol, with photo evidence and GPS location.
     */
    public function addCaseReport(Request $request, PatrollingEntries $entry)
    {
        $this->authorizeOwner($request, $entry);
        $this->assertInProgress($entry);

        $validated = $request->validate([
            'case_number' => ['nullable', 'string', 'max:100', Rule::unique('patrol_case_reports', 'pcr_case_number')],
            'details' => ['required', 'string', 'max:5000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:15360'],
        ]);

        $caseReport = DB::transaction(function () use ($validated, $entry, $request) {
            $caseReport = PatrolCaseReports::create([
                'pcr_entry_id' => $entry->pe_id,
                'pcr_reported_by' => $request->user()->u_id,
                'pcr_case_number' => $validated['case_number'] ?? null,
                'pcr_details' => $validated['details'],
                'pcr_latitude' => $validated['latitude'],
                'pcr_longitude' => $validated['longitude'],
                'pcr_reported_at' => now(),
            ]);

            foreach ($validated['photos'] as $photo) {
                $stored = $this->photos->compressAndStore($photo, 'patrol-case-media/'.$entry->pe_id);

                PatrolCaseMedia::create([
                    'pcm_case_report_id' => $caseReport->pcr_id,
                    'pcm_disk' => 'local',
                    'pcm_file_path' => $stored['path'],
                    'pcm_file_size' => $stored['size'],
                    'pcm_latitude' => $validated['latitude'],
                    'pcm_longitude' => $validated['longitude'],
                ]);
            }

            $entry->pe_incident_occurred = true;

            if (! empty($validated['case_number'])) {
                $entry->pe_case_registered = true;
            }

            $entry->save();

            return $caseReport;
        });

        ReverseGeocodeLocation::dispatch(
            PatrolCaseReports::class,
            $caseReport->pcr_id,
            'pcr_id',
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            'pcr_address'
        );

        $caseReport->load('media');

        return response()->json([
            'success' => true,
            'message' => 'Case report recorded successfully.',
            'data' => new PatrolCaseReportResource($caseReport),
        ], 201);
    }

    /**
     * End the patrol: captures end time/GPS automatically and finalizes vehicle odometer readings.
     */
    public function endPatrol(Request $request, PatrollingEntries $entry)
    {
        $this->authorizeOwner($request, $entry);
        $this->assertInProgress($entry);

        $validated = $request->validate([
            'end_latitude' => ['required', 'numeric', 'between:-90,90'],
            'end_longitude' => ['required', 'numeric', 'between:-180,180'],
            'area_patrolled' => ['nullable', 'string', 'max:5000'],
            'vehicle_odometers' => ['sometimes', 'array'],
            'vehicle_odometers.*.pev_id' => ['required_with:vehicle_odometers', 'uuid'],
            'vehicle_odometers.*.end_odometer' => ['required_with:vehicle_odometers', 'numeric', 'min:0', 'max:9999999.99'],
        ]);

        $entryVehicles = $entry->vehicles()->get()->keyBy('pev_id');

        foreach ($validated['vehicle_odometers'] ?? [] as $reading) {
            $vehicle = $entryVehicles->get($reading['pev_id']);

            if (! $vehicle) {
                throw ValidationException::withMessages([
                    'vehicle_odometers' => "Vehicle {$reading['pev_id']} does not belong to this patrol entry.",
                ]);
            }

            if ($vehicle->pev_start_odometer !== null && $reading['end_odometer'] < $vehicle->pev_start_odometer) {
                throw ValidationException::withMessages([
                    'vehicle_odometers' => "Ending odometer for vehicle {$reading['pev_id']} cannot be less than the starting reading.",
                ]);
            }
        }

        $entry = DB::transaction(function () use ($entry, $validated) {
            foreach ($validated['vehicle_odometers'] ?? [] as $reading) {
                PatrolEntryVehicles::where('pev_id', $reading['pev_id'])
                    ->update(['pev_end_odometer' => $reading['end_odometer']]);
            }

            $totalDistance = $entry->vehicles()->sum('pev_distance');

            $entry->update([
                'pe_end_time' => now()->format('H:i:s'),
                'pe_end_latitude' => $validated['end_latitude'],
                'pe_end_longitude' => $validated['end_longitude'],
                'pe_area_patrolled' => $validated['area_patrolled'] ?? $entry->pe_area_patrolled,
                'pe_total_distance' => $totalDistance,
                'pe_ended_at' => now(),
                'pe_status' => PatrollingEntries::STATUS_COMPLETED,
            ]);

            return $entry;
        });

        ReverseGeocodeLocation::dispatch(
            PatrollingEntries::class,
            $entry->pe_id,
            'pe_id',
            (float) $validated['end_latitude'],
            (float) $validated['end_longitude'],
            'pe_end_address'
        );

        $entry->load(['range', 'beat', 'patrolType', 'modes', 'vehicles.vehicle']);

        return response()->json([
            'success' => true,
            'message' => 'Patrol ended successfully.',
            'data' => new PatrolEntryResource($entry),
        ]);
    }

    private function authorizeOwner(Request $request, PatrollingEntries $entry): void
    {
        if ($entry->pe_patrol_leader_id !== $request->user()->u_id) {
            abort(403, 'You are not the patrol leader for this entry.');
        }
    }

    private function assertInProgress(PatrollingEntries $entry): void
    {
        if ($entry->pe_status !== PatrollingEntries::STATUS_IN_PROGRESS) {
            abort(409, 'This patrol entry has already ended.');
        }
    }

    private function generatePatrolId(Ranges $range, $user, string $date): string
    {
        $rangeCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $range->rn_range_id), 0, 3));
        $employeeId = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $user->u_employee_id));
        $datePart = Carbon::parse($date)->format('Ymd');

        $base = $rangeCode.$employeeId.$datePart;
        $candidate = $base;
        $suffix = 1;

        while (PatrollingEntries::where('pe_patrol_id', $candidate)->exists()) {
            $suffix++;
            $candidate = $base.'-'.$suffix;
        }

        return $candidate;
    }
}
