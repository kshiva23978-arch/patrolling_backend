<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatrolCaseReportResource;
use App\Http\Resources\PatrolEntryResource;
use App\Http\Resources\PatrolIncidentResource;
use App\Jobs\ReverseGeocodeLocation;
use App\Models\Beats;
use App\Models\CaseNumberSequence;
use App\Models\PatrolCaseMedia;
use App\Models\PatrolCaseReports;
use App\Models\PatrolEntryVehicles;
use App\Models\PatrolEntryCustomFieldValue;
use App\Models\PatrolIncident;
use App\Models\PatrolIncidentMedia;
use App\Models\PatrolRoutePoints;
use App\Models\PatrollingEntries;
use App\Models\PatrolTypes;
use App\Models\RangeCustomField;
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
            ->with(['range', 'beat', 'patrolType', 'modes', 'vehicles.vehicle', 'routePoints'])
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

        $entry->load(['range', 'beat', 'patrolType', 'modes', 'vehicles.vehicle', 'caseReports.media', 'incidents.media', 'routePoints', 'customFieldValues.customField']);

        return response()->json([
            'success' => true,
            'message' => 'Patrol entry retrieved successfully.',
            'data' => new PatrolEntryResource($entry),
        ]);
    }

    /**
     * Create a new patrolling entry. The entry starts out "pending" — GPS
     * tracking begins and the status flips to in-progress only once the
     * ranger explicitly calls {@see startPatrol}. Vehicles may optionally
     * be added here too, or later from the dashboard — neither is required
     * to create the entry.
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
            'staff_names' => ['nullable', 'array', 'max:1000'],
            'staff_names.*' => ['string', 'max:150'],
            'mode_ids' => ['required', 'array', 'min:1'],
            'mode_ids.*' => ['uuid', 'distinct', 'exists:patrolling_modes,pm_id'],
            'vehicles' => ['sometimes', 'array'],
            'vehicles.*.type' => ['required_with:vehicles', Rule::in(['2_wheeler', '4_wheeler', 'boat'])],
            'vehicles.*.registration_no' => ['required_with:vehicles', 'string', 'max:50'],
            'vehicles.*.start_odometer' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ]);

        if (! $user->ranges()->where('rn_id', $validated['pe_range_id'])->exists()) {
            throw ValidationException::withMessages([
                'pe_range_id' => 'You do not have access to this range.',
            ]);
        }

        if (isset($validated['staff_names']) && count($validated['staff_names']) > $validated['pe_staff_deployed_count']) {
            throw ValidationException::withMessages([
                'staff_names' => 'Number of named staff cannot exceed the staff deployed count.',
            ]);
        }

        $this->assertNoDuplicateVehicleRegistrations($validated['vehicles'] ?? []);

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

        $vehiclesInput = $validated['vehicles'] ?? [];

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
                'pe_staff_names' => $validated['staff_names'] ?? [],
                'pe_patrol_leader_id' => $user->u_id,
                'pe_gps_enabled' => false,
                'pe_status' => PatrollingEntries::STATUS_PENDING,
            ]);

            $entry->modes()->sync($validated['mode_ids']);

            foreach ($vehiclesInput as $vehicleInput) {
                $vehicle = Vehicles::updateOrCreate(
                    [
                        'vh_range_id' => $range->rn_id,
                        'vh_registration_number' => strtoupper(trim($vehicleInput['registration_no'])),
                    ],
                    [
                        'vh_type' => $vehicleInput['type'] === 'boat' ? 'boat' : 'vehicle',
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

        $entry->load(['range', 'beat', 'patrolType', 'modes', 'vehicles.vehicle', 'caseReports.media', 'incidents.media']);

        return response()->json([
            'success' => true,
            'message' => 'Patrol entry created successfully.',
            'data' => new PatrolEntryResource($entry),
        ], 201);
    }

    /**
     * Start a pending patrol: captures the ranger's current GPS fix as the
     * official start location and flips the entry to in-progress. The
     * ranger must have already picked a current travel mode (walking or one
     * of the entry's vehicles) via {@see setCurrentTravelMode} — a patrol
     * can't start without knowing how the ranger is traveling.
     */
    public function startPatrol(Request $request, PatrollingEntries $entry)
    {
        $this->authorizeOwner($request, $entry);

        if ($entry->pe_status !== PatrollingEntries::STATUS_PENDING) {
            abort(409, 'This patrol has already been started or has ended.');
        }

        if (empty($entry->pe_staff_names)) {
            throw ValidationException::withMessages([
                'staff_names' => 'Add staff names before starting the patrol.',
            ]);
        }

        if ($entry->pe_current_travel_mode === null) {
            throw ValidationException::withMessages([
                'current_travel_mode' => 'Select how you are currently traveling (walking or a vehicle) before starting the patrol.',
            ]);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $entry->update([
            'pe_gps_enabled' => true,
            'pe_start_latitude' => $validated['latitude'],
            'pe_start_longitude' => $validated['longitude'],
            'pe_status' => PatrollingEntries::STATUS_IN_PROGRESS,
            'pe_started_at' => now(),
        ]);

        ReverseGeocodeLocation::dispatch(
            PatrollingEntries::class,
            $entry->pe_id,
            'pe_id',
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            'pe_start_address'
        );

        $entry->load(['range', 'beat', 'patrolType', 'modes', 'vehicles.vehicle', 'caseReports.media', 'incidents.media']);

        return response()->json([
            'success' => true,
            'message' => 'Patrol started successfully.',
            'data' => new PatrolEntryResource($entry),
        ]);
    }

    /**
     * Sets (or clears) how the ranger is currently traveling on this patrol
     * — on foot ("walking"), in one of the entry's own vehicles, or neither.
     * Only one mode is active at a time; the dashboard shows it as a toggle
     * per option.
     */
    public function setCurrentTravelMode(Request $request, PatrollingEntries $entry)
    {
        $this->authorizeOwner($request, $entry);
        $this->assertNotCompleted($entry);

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['walking', 'vehicle', 'none'])],
            'vehicle_id' => ['required_if:mode,vehicle', 'nullable', 'uuid'],
        ]);

        if ($validated['mode'] === 'vehicle') {
            $vehicle = $entry->vehicles()->where('pev_id', $validated['vehicle_id'])->first();

            if (! $vehicle) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'That vehicle does not belong to this patrol entry.',
                ]);
            }

            $entry->update([
                'pe_current_travel_mode' => PatrollingEntries::TRAVEL_MODE_VEHICLE,
                'pe_current_vehicle_id' => $vehicle->pev_id,
            ]);
        } elseif ($validated['mode'] === 'walking') {
            $entry->update([
                'pe_current_travel_mode' => PatrollingEntries::TRAVEL_MODE_WALKING,
                'pe_current_vehicle_id' => null,
            ]);
        } else {
            $entry->update([
                'pe_current_travel_mode' => null,
                'pe_current_vehicle_id' => null,
            ]);
        }

        $entry->load(['range', 'beat', 'patrolType', 'modes', 'vehicles.vehicle', 'caseReports.media', 'incidents.media']);

        return response()->json([
            'success' => true,
            'message' => 'Current travel mode updated.',
            'data' => new PatrolEntryResource($entry),
        ]);
    }

    /**
     * Update the patrol modes, deployed staff names/in-charge, and/or
     * vehicles for a pending or in-progress entry — freely editable (added,
     * renamed, or removed) any time up until the patrol ends. `mode_ids` and
     * `staff_names`, when present, fully replace the existing list. Each
     * item in `vehicles` either edits an existing vehicle (when it carries
     * an `id` already on this entry) or adds a new one (when it doesn't);
     * `remove_vehicle_ids` deletes vehicles outright — removing whichever
     * one is currently the active travel mode clears that selection.
     */
    public function update(Request $request, PatrollingEntries $entry)
    {
        $this->authorizeOwner($request, $entry);
        $this->assertNotCompleted($entry);

        $validated = $request->validate([
            'mode_ids' => ['sometimes', 'array', 'min:1'],
            'mode_ids.*' => ['uuid', 'distinct', 'exists:patrolling_modes,pm_id'],
            'staff_names' => ['sometimes', 'array', 'max:1000'],
            'staff_names.*' => ['string', 'max:150'],
            'incharge_staff' => ['sometimes', 'nullable', 'string', 'max:150'],
            'vehicles' => ['sometimes', 'array'],
            'vehicles.*.id' => ['sometimes', 'uuid'],
            'vehicles.*.type' => ['required_with:vehicles', Rule::in(['2_wheeler', '4_wheeler', 'boat'])],
            'vehicles.*.registration_no' => ['required_with:vehicles', 'string', 'max:50'],
            'vehicles.*.start_odometer' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'remove_vehicle_ids' => ['sometimes', 'array'],
            'remove_vehicle_ids.*' => ['uuid'],
        ]);

        if (isset($validated['staff_names']) && count($validated['staff_names']) > $entry->pe_staff_deployed_count) {
            throw ValidationException::withMessages([
                'staff_names' => 'Number of named staff cannot exceed the staff deployed count.',
            ]);
        }

        if (array_key_exists('incharge_staff', $validated) && $validated['incharge_staff'] !== null) {
            $names = $validated['staff_names'] ?? $entry->pe_staff_names ?? [];

            if (! in_array($validated['incharge_staff'], $names, true)) {
                throw ValidationException::withMessages([
                    'incharge_staff' => 'The in-charge must be one of the deployed staff names.',
                ]);
            }
        }

        $range = $entry->range;
        $vehiclesInput = $validated['vehicles'] ?? [];

        $this->assertNoDuplicateVehicleRegistrations($vehiclesInput);

        $removingIds = $validated['remove_vehicle_ids'] ?? [];
        $editingIds = array_filter(array_column($vehiclesInput, 'id'));

        foreach ($vehiclesInput as $vehicleInput) {
            if (! empty($vehicleInput['id'])) {
                continue;
            }

            $key = strtoupper(trim($vehicleInput['registration_no']));

            $alreadyAttached = $entry->vehicles()
                ->whereNotIn('pev_id', array_merge($removingIds, $editingIds))
                ->whereHas('vehicle', fn ($q) => $q->where('vh_registration_number', $key))
                ->exists();

            if ($alreadyAttached) {
                throw ValidationException::withMessages([
                    'vehicles' => "Vehicle \"{$vehicleInput['registration_no']}\" is already on this patrol — edit it instead of adding it again.",
                ]);
            }
        }

        DB::transaction(function () use ($entry, $validated, $range, $vehiclesInput) {
            if (isset($validated['mode_ids'])) {
                $entry->modes()->sync($validated['mode_ids']);
            }

            if (isset($validated['staff_names'])) {
                $entry->pe_staff_names = $validated['staff_names'];

                if ($entry->pe_incharge_staff !== null && ! in_array($entry->pe_incharge_staff, $validated['staff_names'], true)) {
                    $entry->pe_incharge_staff = null;
                }

                $entry->save();
            }

            if (array_key_exists('incharge_staff', $validated)) {
                $entry->pe_incharge_staff = $validated['incharge_staff'];
                $entry->save();
            }

            foreach ($vehiclesInput as $vehicleInput) {
                $vehicle = Vehicles::updateOrCreate(
                    [
                        'vh_range_id' => $range->rn_id,
                        'vh_registration_number' => strtoupper(trim($vehicleInput['registration_no'])),
                    ],
                    [
                        'vh_type' => $vehicleInput['type'] === 'boat' ? 'boat' : 'vehicle',
                    ]
                );

                if (! empty($vehicleInput['id'])) {
                    $entryVehicle = $entry->vehicles()->where('pev_id', $vehicleInput['id'])->first();

                    if (! $entryVehicle) {
                        throw ValidationException::withMessages([
                            'vehicles' => 'One of the vehicles being edited does not belong to this patrol entry.',
                        ]);
                    }

                    $entryVehicle->update([
                        'pev_vehicle_id' => $vehicle->vh_id,
                        'pev_vehicle_type' => $vehicleInput['type'],
                        'pev_start_odometer' => $vehicleInput['start_odometer'] ?? null,
                    ]);
                } else {
                    PatrolEntryVehicles::create([
                        'pev_entry_id' => $entry->pe_id,
                        'pev_vehicle_id' => $vehicle->vh_id,
                        'pev_vehicle_type' => $vehicleInput['type'],
                        'pev_start_odometer' => $vehicleInput['start_odometer'] ?? null,
                    ]);
                }
            }

            if (! empty($validated['remove_vehicle_ids'])) {
                $toRemove = $entry->vehicles()->whereIn('pev_id', $validated['remove_vehicle_ids'])->get();

                foreach ($toRemove as $vehicle) {
                    if ($entry->pe_current_vehicle_id === $vehicle->pev_id) {
                        $entry->pe_current_travel_mode = null;
                        $entry->pe_current_vehicle_id = null;
                        $entry->save();
                    }
                    $vehicle->delete();
                }
            }
        });

        $entry->load(['range', 'beat', 'patrolType', 'modes', 'vehicles.vehicle', 'caseReports.media', 'incidents.media']);

        return response()->json([
            'success' => true,
            'message' => 'Patrol entry updated successfully.',
            'data' => new PatrolEntryResource($entry),
        ]);
    }

    /**
     * Record a live GPS ping while the patrol is in progress (the app polls
     * this every 30s). Snapshots the entry's current travel mode/vehicle at
     * ping time — set via {@see setCurrentTravelMode} — so the route trail
     * shows how the ranger was traveling at each point, even if that
     * changes mid-patrol.
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
            'prp_travel_mode' => $entry->pe_current_travel_mode,
            'prp_vehicle_id' => $entry->pe_current_vehicle_id,
            'prp_recorded_at' => $validated['recorded_at'] ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'GPS point recorded.',
            'data' => [
                'id' => $point->prp_id,
                'travel_mode' => $point->prp_travel_mode,
                'recorded_at' => $point->prp_recorded_at->toISOString(),
            ],
        ], 201);
    }

    /**
     * Log a lightweight incident during the patrol — quicker to file than a
     * full case report ({@see addCaseReport}), with an optional geo-tagged
     * photo. The app then asks the ranger whether to escalate it into a
     * full case report.
     */
    public function addIncident(Request $request, PatrollingEntries $entry)
    {
        $this->authorizeOwner($request, $entry);
        $this->assertInProgress($entry);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'details' => ['required', 'string', 'max:5000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photo' => ['sometimes', 'image', 'mimes:jpeg,jpg,png,webp', 'max:15360'],
        ]);

        $incident = DB::transaction(function () use ($validated, $entry, $request) {
            $incident = PatrolIncident::create([
                'pi_entry_id' => $entry->pe_id,
                'pi_reported_by' => $request->user()->u_id,
                'pi_name' => $validated['name'],
                'pi_details' => $validated['details'],
                'pi_latitude' => $validated['latitude'] ?? null,
                'pi_longitude' => $validated['longitude'] ?? null,
                'pi_reported_at' => now(),
            ]);

            if ($request->hasFile('photo')) {
                $stored = $this->photos->compressAndStore($request->file('photo'), 'patrol-incident-media/'.$entry->pe_id);

                PatrolIncidentMedia::create([
                    'pim_incident_id' => $incident->pi_id,
                    'pim_disk' => 'local',
                    'pim_file_path' => $stored['path'],
                    'pim_file_size' => $stored['size'],
                    'pim_latitude' => $validated['latitude'] ?? null,
                    'pim_longitude' => $validated['longitude'] ?? null,
                ]);
            }

            return $incident;
        });

        if (! empty($validated['latitude'])) {
            ReverseGeocodeLocation::dispatch(
                PatrolIncident::class,
                $incident->pi_id,
                'pi_id',
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                'pi_address'
            );
        }

        $incident->load('media');

        return response()->json([
            'success' => true,
            'message' => 'Incident recorded successfully.',
            'data' => new PatrolIncidentResource($incident),
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
            'details' => ['required', 'string', 'max:5000'],
            'conflict_type' => ['nullable', 'string', 'max:100'],
            'rescue_conducted' => ['nullable', 'boolean'],
            'species_rescued' => ['nullable', 'string', 'max:100'],
            'rehab_details' => ['nullable', 'string', 'max:2000'],
            'response_time' => ['nullable', 'date_format:H:i'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photos' => ['sometimes', 'array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:15360'],
        ]);

        $caseReport = DB::transaction(function () use ($validated, $entry, $request) {
            $caseReport = PatrolCaseReports::create([
                'pcr_entry_id' => $entry->pe_id,
                'pcr_reported_by' => $request->user()->u_id,
                'pcr_case_number' => $this->generateCaseNumber(),
                'pcr_details' => $validated['details'],
                'pcr_conflict_type' => $validated['conflict_type'] ?? null,
                'pcr_rescue_conducted' => $validated['rescue_conducted'] ?? null,
                'pcr_species_rescued' => $validated['species_rescued'] ?? null,
                'pcr_rehab_details' => $validated['rehab_details'] ?? null,
                'pcr_response_time' => $validated['response_time'] ?? null,
                'pcr_latitude' => $validated['latitude'],
                'pcr_longitude' => $validated['longitude'],
                'pcr_reported_at' => now(),
            ]);

            foreach ($validated['photos'] ?? [] as $photo) {
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
            'report' => ['nullable', 'string', 'max:5000'],
            'vehicle_odometers' => ['sometimes', 'array'],
            'vehicle_odometers.*.pev_id' => ['required_with:vehicle_odometers', 'uuid'],
            'vehicle_odometers.*.end_odometer' => ['required_with:vehicle_odometers', 'numeric', 'min:0', 'max:9999999.99'],
            'custom_field_values' => ['sometimes', 'array'],
            'custom_field_values.*.custom_field_id' => ['required_with:custom_field_values', 'uuid'],
            'custom_field_values.*.value' => ['nullable'],
        ]);

        $customFieldValues = $this->resolveCustomFieldValues($entry, $validated['custom_field_values'] ?? []);

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

        $entry = DB::transaction(function () use ($entry, $validated, $customFieldValues) {
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
                'pe_remarks' => $validated['report'] ?? $entry->pe_remarks,
                'pe_total_distance' => $totalDistance,
                'pe_ended_at' => now(),
                'pe_status' => PatrollingEntries::STATUS_COMPLETED,
            ]);

            foreach ($customFieldValues as $customFieldId => $value) {
                PatrolEntryCustomFieldValue::updateOrCreate(
                    ['pcfv_entry_id' => $entry->pe_id, 'pcfv_custom_field_id' => $customFieldId],
                    ['pcfv_value' => $value]
                );
            }

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

        $entry->load(['range', 'beat', 'patrolType', 'modes', 'vehicles.vehicle', 'caseReports.media', 'incidents.media', 'customFieldValues.customField']);

        return response()->json([
            'success' => true,
            'message' => 'Patrol ended successfully.',
            'data' => new PatrolEntryResource($entry),
        ]);
    }

    /**
     * Validates the `custom_field_values` payload against the entry's
     * range's active {@see RangeCustomField} definitions — every required
     * field must carry a non-empty value, a dropdown's value must be one of
     * its configured options, and any field id not belonging to (or not
     * active for) this range is rejected outright — then returns the values
     * to persist, keyed by custom field id and stringified for storage.
     *
     * @param  array<int, array{custom_field_id: string, value: mixed}>  $submitted
     * @return array<string, string>
     */
    private function resolveCustomFieldValues(PatrollingEntries $entry, array $submitted): array
    {
        $fields = RangeCustomField::where('rcf_range_id', $entry->pe_range_id)
            ->where('rcf_is_active', true)
            ->get()
            ->keyBy('rcf_id');

        $submittedByFieldId = [];
        foreach ($submitted as $row) {
            $fieldId = $row['custom_field_id'];

            if (! $fields->has($fieldId)) {
                throw ValidationException::withMessages([
                    'custom_field_values' => 'One of the custom fields submitted does not belong to this range.',
                ]);
            }

            $submittedByFieldId[$fieldId] = $row['value'] ?? null;
        }

        $resolved = [];

        foreach ($fields as $fieldId => $field) {
            $value = $submittedByFieldId[$fieldId] ?? null;
            $isEmpty = $value === null || $value === '';

            if ($field->rcf_is_required && $isEmpty) {
                throw ValidationException::withMessages([
                    'custom_field_values' => "\"{$field->rcf_field_name}\" is required.",
                ]);
            }

            if ($isEmpty) {
                continue;
            }

            if ($field->rcf_input_type === RangeCustomField::TYPE_DROPDOWN
                && ! in_array((string) $value, $field->rcf_options ?? [], true)) {
                throw ValidationException::withMessages([
                    'custom_field_values' => "\"{$value}\" is not a valid option for \"{$field->rcf_field_name}\".",
                ]);
            }

            $resolved[$fieldId] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }

        return $resolved;
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

    private function assertNotCompleted(PatrollingEntries $entry): void
    {
        if ($entry->pe_status === PatrollingEntries::STATUS_COMPLETED) {
            abort(409, 'This patrol entry has already ended.');
        }
    }

    /**
     * Rejects a `vehicles` payload that lists the same registration number
     * more than once — guards against a double-tapped save (or a copy/paste
     * mistake) turning into two separate rows for what's really one vehicle.
     *
     * @param  array<int, array{registration_no?: string}>  $vehicles
     */
    private function assertNoDuplicateVehicleRegistrations(array $vehicles): void
    {
        $seen = [];

        foreach ($vehicles as $vehicleInput) {
            $key = strtoupper(trim($vehicleInput['registration_no'] ?? ''));

            if ($key === '') {
                continue;
            }

            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    'vehicles' => "Vehicle \"{$vehicleInput['registration_no']}\" is listed more than once.",
                ]);
            }

            $seen[$key] = true;
        }
    }

    /**
     * Issues the next case number for the current year (e.g. `CASE-2026-00042`)
     * from the {@see CaseNumberSequence} master, locking the row so
     * concurrent case reports never collide.
     */
    private function generateCaseNumber(): string
    {
        $year = (int) now()->year;

        CaseNumberSequence::firstOrCreate(['cns_year' => $year], ['cns_last_number' => 0]);

        $sequence = CaseNumberSequence::where('cns_year', $year)->lockForUpdate()->first();
        $sequence->increment('cns_last_number');

        return sprintf('CASE-%d-%05d', $year, $sequence->cns_last_number);
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
