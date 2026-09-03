<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CaseEntryCommentResource;
use App\Http\Resources\CaseEntryFilingResource;
use App\Http\Resources\CaseEntryIncidentResource;
use App\Http\Resources\CaseEntryNoteResource;
use App\Http\Resources\CaseEntryResource;
use App\Http\Resources\CaseEntryRoutePointResource;
use App\Jobs\ReverseGeocodeLocation;
use App\Models\Beats;
use App\Models\CaseEntry;
use App\Models\CaseEntryClosingMedia;
use App\Models\CaseEntryComment;
use App\Models\CaseEntryFiling;
use App\Models\CaseEntryFilingMedia;
use App\Models\CaseEntryIncident;
use App\Models\CaseEntryIncidentMedia;
use App\Models\CaseEntryNote;
use App\Models\CaseEntryNumberSequence;
use App\Models\CaseEntryRoutePoint;
use App\Models\CaseEntryVehicle;
use App\Models\Ranges;
use App\Models\Vehicles;
use App\Services\PatrolPhotoService;
use App\Services\UnfinishedWorkChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A standalone ranger-led investigation/pursuit ("Case") — see the
 * `create_case_entries_table` migration and {@see CaseEntry} for why this is
 * deliberately independent of the Patrol module rather than reusing
 * `PatrollingEntries`/`pe_type='case'`. Structured to mirror
 * {@see PatrolEntryController} method-for-method; deltas are called out
 * inline as they come up (free-text case type instead of a patrol-type FK,
 * mandatory minimum photo counts on Add Incident/File Case/Close Case, and
 * the cross-module "one active Patrol/Case/Activity at a time" rule).
 */
class CaseEntryController extends Controller
{
    public function __construct(
        private readonly PatrolPhotoService $photos,
        private readonly UnfinishedWorkChecker $unfinishedWork,
    ) {}

    /**
     * Free-text values already used for [field] within [range_id] — powers
     * autocomplete suggestions the same way
     * {@see PatrolEntryController::fieldSuggestions} does for the patrol
     * module, scoped to this range's past cases only.
     */
    public function fieldSuggestions(Request $request)
    {
        $validated = $request->validate([
            'range_id' => ['required', 'uuid', 'exists:ranges,rn_id'],
            'field' => ['required', Rule::in([
                'case_type', 'staff_name', 'incident_name', 'incident_details',
                'filing_conflict_type', 'filing_details', 'filing_species',
            ])],
        ]);

        $rangeId = $validated['range_id'];
        $limit = 20;

        $inRange = fn ($q) => $q->where('ce_range_id', $rangeId);

        $values = match ($validated['field']) {
            'case_type' => $this->rankedDistinctValues(
                CaseEntry::where('ce_range_id', $rangeId), 'ce_case_type', $limit,
            ),
            'staff_name' => CaseEntry::where('ce_range_id', $rangeId)
                ->whereNotNull('ce_staff_names')
                ->pluck('ce_staff_names')
                ->flatten()
                ->map(fn ($name) => trim((string) $name))
                ->filter(fn ($name) => $name !== '')
                ->countBy()
                ->sortDesc()
                ->keys()
                ->take($limit)
                ->values()
                ->all(),
            'incident_name' => $this->rankedDistinctValues(
                CaseEntryIncident::whereHas('case', $inRange), 'cei_name', $limit,
            ),
            'incident_details' => $this->rankedDistinctValues(
                CaseEntryIncident::whereHas('case', $inRange), 'cei_details', $limit,
            ),
            'filing_conflict_type' => $this->rankedDistinctValues(
                CaseEntryFiling::whereHas('case', $inRange), 'cef_conflict_type', $limit,
            ),
            'filing_details' => $this->rankedDistinctValues(
                CaseEntryFiling::whereHas('case', $inRange), 'cef_details', $limit,
            ),
            'filing_species' => $this->rankedDistinctValues(
                CaseEntryFiling::whereHas('case', $inRange), 'cef_species_rescued', $limit,
            ),
        };

        return response()->json([
            'success' => true,
            'message' => 'Suggestions retrieved successfully.',
            'data' => $values,
        ]);
    }

    private function rankedDistinctValues($query, string $column, int $limit): array
    {
        return $query->whereNotNull($column)
            ->where($column, '!=', '')
            ->select($column)
            ->selectRaw('COUNT(*) as usage_count')
            ->groupBy($column)
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->pluck($column)
            ->all();
    }

    /** See `PatrolEntryController::index`'s identical doc comment. */
    public function index(Request $request)
    {
        $rangeIds = $request->user()->ranges()->pluck('ranges.rn_id');

        $cases = CaseEntry::query()
            ->where(function ($query) use ($request, $rangeIds) {
                $query->where('ce_leader_id', $request->user()->u_id)
                    ->orWhereIn('ce_range_id', $rangeIds);
            })
            ->with([
                'range', 'beat', 'modes', 'vehicles.vehicle', 'incidents.media', 'filings.media',
                'notes', 'comments.admin', 'comments.user.details',
            ])
            ->latest('ce_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Cases retrieved successfully.',
            'data' => CaseEntryResource::collection($cases),
            'meta' => [
                'current_page' => $cases->currentPage(),
                'per_page' => $cases->perPage(),
                'total' => $cases->total(),
                'last_page' => $cases->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, CaseEntry $case)
    {
        if (! $this->canViewEntry($request, $case)) {
            abort(403, 'You do not have access to this case.');
        }

        $case->load(['range', 'beat', 'modes', 'vehicles.vehicle', 'incidents.media', 'filings.media', 'notes', 'closingMedia', 'comments.admin', 'comments.user.details']);

        return response()->json([
            'success' => true,
            'message' => 'Case retrieved successfully.',
            'data' => new CaseEntryResource($case),
        ]);
    }

    public function routePoints(Request $request, CaseEntry $case)
    {
        if (! $this->canViewEntry($request, $case)) {
            abort(403, 'You do not have access to this case.');
        }

        $points = $case->routePoints()->with('vehicle')->orderBy('cerp_recorded_at')->get();

        return response()->json([
            'success' => true,
            'message' => 'Route points retrieved successfully.',
            'data' => CaseEntryRoutePointResource::collection($points),
        ]);
    }

    /**
     * Create a new case — starts out "pending", same three-state lifecycle
     * as a patrol entry. Blocked if the ranger already has an in-progress
     * Patrol, Case, or Activity (see {@see UnfinishedWorkChecker}) — a
     * ranger can only be actively doing one at a time.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->hasAppFeature('case')) {
            abort(403, "You don't have permission to create a case.");
        }

        $validated = $request->validate([
            // Optional client-generated id: the app can create a case while
            // offline and start using it locally before this request ever
            // reaches the server — same idempotent-offline-create pattern as
            // PatrollingEntries::store's `pe_id`.
            'ce_id' => ['sometimes', 'uuid'],
            'ce_date' => ['required', 'date', 'before_or_equal:today'],
            'ce_start_time' => ['required', 'date_format:H:i'],
            'ce_range_id' => ['required', 'uuid', 'exists:ranges,rn_id'],
            'ce_beat_id' => ['nullable', 'uuid', 'exists:beats,bt_id'],
            'ce_area_covered' => ['nullable', 'string', 'max:255'],
            'ce_case_type' => ['required', 'string', 'max:150'],
            'ce_staff_deployed_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'staff_names' => ['nullable', 'array', 'max:1000'],
            'staff_names.*' => ['string', 'max:150'],
            'mode_ids' => ['required', 'array', 'min:1'],
            'mode_ids.*' => ['uuid', 'distinct', 'exists:patrolling_modes,pm_id'],
            'vehicles' => ['sometimes', 'array'],
            'vehicles.*.type' => ['required_with:vehicles', Rule::in(['2_wheeler', '4_wheeler', 'boat'])],
            'vehicles.*.registration_no' => ['required_with:vehicles', 'string', 'max:50'],
            'vehicles.*.start_odometer' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ]);

        if (! empty($validated['ce_id'])) {
            $existing = CaseEntry::where('ce_id', $validated['ce_id'])
                ->where('ce_leader_id', $user->u_id)
                ->first();

            if ($existing) {
                $existing->load(['range', 'beat', 'modes', 'vehicles.vehicle', 'incidents.media', 'filings.media']);

                return response()->json([
                    'success' => true,
                    'message' => 'Case created successfully.',
                    'data' => new CaseEntryResource($existing),
                ], 201);
            }
        }

        if (! $user->ranges()->where('rn_id', $validated['ce_range_id'])->exists()) {
            throw ValidationException::withMessages([
                'ce_range_id' => 'You do not have access to this range.',
            ]);
        }

        $tokenId = $user->currentAccessToken()?->id;

        if ($this->unfinishedWork->hasInProgressWork($user->u_id, $tokenId)) {
            abort(409, 'You already have a patrol, case, or activity that has not ended yet. End it before starting a new one.');
        }

        if (isset($validated['staff_names']) && count($validated['staff_names']) > $validated['ce_staff_deployed_count']) {
            throw ValidationException::withMessages([
                'staff_names' => 'Number of named staff cannot exceed the staff deployed count.',
            ]);
        }

        $this->assertNoDuplicateVehicleRegistrations($validated['vehicles'] ?? []);

        $range = Ranges::findOrFail($validated['ce_range_id']);

        if (! empty($validated['ce_beat_id'])) {
            $beat = Beats::findOrFail($validated['ce_beat_id']);

            if ($beat->bt_range_id !== $range->rn_id) {
                throw ValidationException::withMessages([
                    'ce_beat_id' => 'The selected beat does not belong to the selected range.',
                ]);
            }
        }

        $vehiclesInput = $validated['vehicles'] ?? [];

        $case = DB::transaction(function () use ($validated, $user, $range, $vehiclesInput, $tokenId) {
            $case = CaseEntry::create([
                'ce_id' => $validated['ce_id'] ?? null,
                'ce_case_number' => $this->generateCaseNumber($range),
                'ce_date' => $validated['ce_date'],
                'ce_start_time' => $validated['ce_start_time'],
                'ce_range_id' => $range->rn_id,
                'ce_beat_id' => $validated['ce_beat_id'] ?? null,
                'ce_area_covered' => $validated['ce_area_covered'] ?? null,
                'ce_case_type' => trim($validated['ce_case_type']),
                'ce_staff_deployed_count' => $validated['ce_staff_deployed_count'],
                'ce_staff_names' => $validated['staff_names'] ?? [],
                'ce_leader_id' => $user->u_id,
                'ce_created_via_token_id' => $tokenId,
                'ce_status' => CaseEntry::STATUS_PENDING,
            ]);

            $case->modes()->sync($validated['mode_ids']);

            foreach ($vehiclesInput as $vehicleInput) {
                // Same firstOrCreate-by-registration reasoning as
                // PatrolEntryController::store — see that comment.
                $vehicle = Vehicles::firstOrCreate(
                    ['vh_registration_number' => strtoupper(trim($vehicleInput['registration_no']))],
                    [
                        'vh_range_id' => $range->rn_id,
                        'vh_type' => $vehicleInput['type'] === 'boat' ? 'boat' : 'vehicle',
                    ]
                );

                CaseEntryVehicle::create([
                    'cev_case_id' => $case->ce_id,
                    'cev_vehicle_id' => $vehicle->vh_id,
                    'cev_vehicle_type' => $vehicleInput['type'],
                    'cev_start_odometer' => $vehicleInput['start_odometer'] ?? null,
                ]);
            }

            return $case;
        });

        $case->load(['range', 'beat', 'modes', 'vehicles.vehicle', 'incidents.media', 'filings.media']);

        return response()->json([
            'success' => true,
            'message' => 'Case created successfully.',
            'data' => new CaseEntryResource($case),
        ], 201);
    }

    /**
     * Start a pending case: captures the ranger's current GPS fix as the
     * start location and flips the entry to in-progress. Requires a current
     * travel mode first, same as {@see PatrolEntryController::startPatrol}.
     */
    public function startCase(Request $request, CaseEntry $case)
    {
        $this->authorizeOwner($request, $case);

        if ($case->ce_status !== CaseEntry::STATUS_PENDING) {
            abort(409, 'This case has already been started or has ended.');
        }

        if (empty($case->ce_staff_names)) {
            throw ValidationException::withMessages([
                'staff_names' => 'Add staff names before starting the case.',
            ]);
        }

        if ($case->ce_current_travel_mode === null) {
            throw ValidationException::withMessages([
                'current_travel_mode' => 'Select how you are currently traveling (walking or a vehicle) before starting the case.',
            ]);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'started_at' => ['sometimes', 'date', 'before_or_equal:now'],
            // Proves the ranger themself is the one starting this case —
            // mandatory here too, same as a patrol's own start selfie (see
            // PatrolEntryController::startPatrol).
            'selfie' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:15360'],
        ]);

        $storedSelfie = $this->photos->compressAndStore($validated['selfie'], 'case-start-selfies/'.$case->ce_id);

        $case->update([
            'ce_start_latitude' => $validated['latitude'],
            'ce_start_longitude' => $validated['longitude'],
            'ce_start_selfie_disk' => 'local',
            'ce_start_selfie_path' => $storedSelfie['path'],
            'ce_status' => CaseEntry::STATUS_IN_PROGRESS,
            // See PatrolEntryController::startPatrol's `pe_started_at` comment —
            // `ce_started_at` is the same kind of naive column, so a
            // client-submitted (real UTC) value must be converted to the app's
            // timezone before it's saved.
            'ce_started_at' => isset($validated['started_at'])
                ? Carbon::parse($validated['started_at'])->setTimezone(config('app.timezone'))
                : now(),
        ]);

        ReverseGeocodeLocation::dispatch(
            CaseEntry::class,
            $case->ce_id,
            'ce_id',
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            'ce_start_address'
        );

        $case->load(['range', 'beat', 'modes', 'vehicles.vehicle', 'incidents.media', 'filings.media']);

        return response()->json([
            'success' => true,
            'message' => 'Case started successfully.',
            'data' => new CaseEntryResource($case),
        ]);
    }

    /**
     * Deletes a case the ranger hasn't started yet — see
     * {@see PatrolEntryController::destroy}.
     */
    public function destroy(Request $request, CaseEntry $case)
    {
        $this->authorizeOwner($request, $case);

        if ($case->ce_status !== CaseEntry::STATUS_PENDING) {
            abort(409, "Only a case that hasn't started yet can be deleted.");
        }

        $case->delete();

        return response()->json([
            'success' => true,
            'message' => 'Case deleted successfully.',
        ]);
    }

    public function setCurrentTravelMode(Request $request, CaseEntry $case)
    {
        $this->authorizeOwner($request, $case);
        $this->assertNotCompleted($case);

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['walking', 'vehicle', 'none'])],
            'vehicle_id' => ['required_if:mode,vehicle', 'nullable', 'uuid'],
        ]);

        if ($validated['mode'] === 'vehicle') {
            $vehicle = $case->vehicles()->where('cev_id', $validated['vehicle_id'])->first();

            if (! $vehicle) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'That vehicle does not belong to this case.',
                ]);
            }

            $case->update([
                'ce_current_travel_mode' => CaseEntry::TRAVEL_MODE_VEHICLE,
                'ce_current_vehicle_id' => $vehicle->cev_id,
            ]);
        } elseif ($validated['mode'] === 'walking') {
            $case->update([
                'ce_current_travel_mode' => CaseEntry::TRAVEL_MODE_WALKING,
                'ce_current_vehicle_id' => null,
            ]);
        } else {
            $case->update([
                'ce_current_travel_mode' => null,
                'ce_current_vehicle_id' => null,
            ]);
        }

        $case->load(['range', 'beat', 'modes', 'vehicles.vehicle', 'incidents.media', 'filings.media']);

        return response()->json([
            'success' => true,
            'message' => 'Current travel mode updated.',
            'data' => new CaseEntryResource($case),
        ]);
    }

    /**
     * Update modes/staff/vehicles for a pending or in-progress case — same
     * shape as {@see PatrolEntryController::update}, including the same
     * lack of a status guard (see that method's doc comment).
     */
    public function update(Request $request, CaseEntry $case)
    {
        $this->authorizeOwner($request, $case);

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

        if (isset($validated['staff_names']) && count($validated['staff_names']) > $case->ce_staff_deployed_count) {
            throw ValidationException::withMessages([
                'staff_names' => 'Number of named staff cannot exceed the staff deployed count.',
            ]);
        }

        if (array_key_exists('incharge_staff', $validated) && $validated['incharge_staff'] !== null) {
            $names = $validated['staff_names'] ?? $case->ce_staff_names ?? [];

            if (! in_array($validated['incharge_staff'], $names, true)) {
                throw ValidationException::withMessages([
                    'incharge_staff' => 'The in-charge must be one of the deployed staff names.',
                ]);
            }
        }

        $range = $case->range;
        $vehiclesInput = $validated['vehicles'] ?? [];

        $this->assertNoDuplicateVehicleRegistrations($vehiclesInput);

        $removingIds = $validated['remove_vehicle_ids'] ?? [];
        $editingIds = array_filter(array_column($vehiclesInput, 'id'));

        foreach ($vehiclesInput as $vehicleInput) {
            if (! empty($vehicleInput['id'])) {
                continue;
            }

            $key = strtoupper(trim($vehicleInput['registration_no']));

            $alreadyAttached = $case->vehicles()
                ->whereNotIn('cev_id', array_merge($removingIds, $editingIds))
                ->whereHas('vehicle', fn ($q) => $q->where('vh_registration_number', $key))
                ->exists();

            if ($alreadyAttached) {
                throw ValidationException::withMessages([
                    'vehicles' => "Vehicle \"{$vehicleInput['registration_no']}\" is already on this case — edit it instead of adding it again.",
                ]);
            }
        }

        DB::transaction(function () use ($case, $validated, $range, $vehiclesInput) {
            if (isset($validated['mode_ids'])) {
                $case->modes()->sync($validated['mode_ids']);
            }

            if (isset($validated['staff_names'])) {
                $case->ce_staff_names = $validated['staff_names'];

                if ($case->ce_incharge_staff !== null && ! in_array($case->ce_incharge_staff, $validated['staff_names'], true)) {
                    $case->ce_incharge_staff = null;
                }

                $case->save();
            }

            if (array_key_exists('incharge_staff', $validated)) {
                $case->ce_incharge_staff = $validated['incharge_staff'];
                $case->save();
            }

            foreach ($vehiclesInput as $vehicleInput) {
                $vehicle = Vehicles::firstOrCreate(
                    ['vh_registration_number' => strtoupper(trim($vehicleInput['registration_no']))],
                    [
                        'vh_range_id' => $range->rn_id,
                        'vh_type' => $vehicleInput['type'] === 'boat' ? 'boat' : 'vehicle',
                    ]
                );

                if (! empty($vehicleInput['id'])) {
                    $caseVehicle = $case->vehicles()->where('cev_id', $vehicleInput['id'])->first();

                    if (! $caseVehicle) {
                        throw ValidationException::withMessages([
                            'vehicles' => 'One of the vehicles being edited does not belong to this case.',
                        ]);
                    }

                    $caseVehicle->update([
                        'cev_vehicle_id' => $vehicle->vh_id,
                        'cev_vehicle_type' => $vehicleInput['type'],
                        'cev_start_odometer' => $vehicleInput['start_odometer'] ?? null,
                    ]);
                } else {
                    CaseEntryVehicle::create([
                        'cev_case_id' => $case->ce_id,
                        'cev_vehicle_id' => $vehicle->vh_id,
                        'cev_vehicle_type' => $vehicleInput['type'],
                        'cev_start_odometer' => $vehicleInput['start_odometer'] ?? null,
                    ]);
                }
            }

            if (! empty($validated['remove_vehicle_ids'])) {
                $toRemove = $case->vehicles()->whereIn('cev_id', $validated['remove_vehicle_ids'])->get();

                foreach ($toRemove as $vehicle) {
                    if ($case->ce_current_vehicle_id === $vehicle->cev_id) {
                        $case->ce_current_travel_mode = null;
                        $case->ce_current_vehicle_id = null;
                        $case->save();
                    }
                    $vehicle->delete();
                }
            }
        });

        $case->load(['range', 'beat', 'modes', 'vehicles.vehicle', 'incidents.media', 'filings.media']);

        return response()->json([
            'success' => true,
            'message' => 'Case updated successfully.',
            'data' => new CaseEntryResource($case),
        ]);
    }

    public function addGpsPing(Request $request, CaseEntry $case)
    {
        $this->authorizeOwner($request, $case);
        $this->assertInProgress($case);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $point = CaseEntryRoutePoint::create([
            'cerp_case_id' => $case->ce_id,
            'cerp_latitude' => $validated['latitude'],
            'cerp_longitude' => $validated['longitude'],
            'cerp_travel_mode' => $case->ce_current_travel_mode,
            'cerp_vehicle_id' => $case->ce_current_vehicle_id,
            // See PatrolEntryController::addGpsPing()'s `prp_recorded_at` for
            // why the client's (real UTC) value must be converted before
            // saving into this naive-column timestamp.
            'cerp_recorded_at' => isset($validated['recorded_at'])
                ? Carbon::parse($validated['recorded_at'])->setTimezone(config('app.timezone'))
                : now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'GPS point recorded.',
            'data' => [
                'id' => $point->cerp_id,
                'travel_mode' => $point->cerp_travel_mode,
                'recorded_at' => $point->cerp_recorded_at->toISOString(),
            ],
        ], 201);
    }

    /**
     * Log an incident during the case — unlike the patrol module's
     * incidents, at least {@see CaseEntry::MIN_PHOTOS} photos are mandatory.
     */
    public function addIncident(Request $request, CaseEntry $case)
    {
        $this->authorizeOwner($request, $case);
        $this->assertStarted($case);

        $validated = $request->validate([
            // See PatrolEntryController::addIncident()'s `client_id` for why.
            'client_id' => ['sometimes', 'uuid'],
            'name' => ['required', 'string', 'max:150'],
            'details' => ['required', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(['open', 'closed'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photos' => ['required', 'array', 'min:'.CaseEntry::MIN_PHOTOS, 'max:20'],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:15360'],
            // See PatrolEntryController::addIncident()'s `reported_at` for
            // why — same offline-sync purpose.
            'reported_at' => ['sometimes', 'date', 'before_or_equal:now'],
        ]);

        if (! empty($validated['client_id'])) {
            $existing = CaseEntryIncident::where('cei_case_id', $case->ce_id)
                ->where('cei_client_id', $validated['client_id'])
                ->first();

            if ($existing) {
                $existing->load('media');

                return response()->json([
                    'success' => true,
                    'message' => 'Incident recorded successfully.',
                    'data' => new CaseEntryIncidentResource($existing),
                ], 201);
            }
        }

        $incident = DB::transaction(function () use ($validated, $case, $request) {
            $incident = CaseEntryIncident::create([
                'cei_case_id' => $case->ce_id,
                'cei_client_id' => $validated['client_id'] ?? null,
                'cei_reported_by' => $request->user()->u_id,
                'cei_name' => $validated['name'],
                'cei_details' => $validated['details'],
                'cei_status' => $validated['status'] ?? 'open',
                'cei_latitude' => $validated['latitude'] ?? null,
                'cei_longitude' => $validated['longitude'] ?? null,
                'cei_reported_at' => isset($validated['reported_at'])
                    ? Carbon::parse($validated['reported_at'])->setTimezone(config('app.timezone'))
                    : now(),
            ]);

            foreach ($validated['photos'] as $photo) {
                $stored = $this->photos->compressAndStore($photo, 'case-incident-media/'.$case->ce_id);

                CaseEntryIncidentMedia::create([
                    'ceim_incident_id' => $incident->cei_id,
                    'ceim_disk' => 'local',
                    'ceim_file_path' => $stored['path'],
                    'ceim_file_size' => $stored['size'],
                    'ceim_latitude' => $validated['latitude'] ?? null,
                    'ceim_longitude' => $validated['longitude'] ?? null,
                ]);
            }

            $case->ce_incident_occurred = true;
            $case->save();

            return $incident;
        });

        if (! empty($validated['latitude'])) {
            ReverseGeocodeLocation::dispatch(
                CaseEntryIncident::class,
                $incident->cei_id,
                'cei_id',
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                'cei_address'
            );
        }

        $incident->load('media');

        return response()->json([
            'success' => true,
            'message' => 'Incident recorded successfully.',
            'data' => new CaseEntryIncidentResource($incident),
        ], 201);
    }

    /**
     * "File Case": a rescue/legal filing against the case, with rescue
     * fields and, like {@see addIncident}, at least
     * {@see CaseEntry::MIN_PHOTOS} mandatory photos.
     */
    public function addFiling(Request $request, CaseEntry $case)
    {
        $this->authorizeOwner($request, $case);
        $this->assertStarted($case);

        $validated = $request->validate([
            // See PatrolEntryController::addIncident()'s `client_id` for why.
            'client_id' => ['sometimes', 'uuid'],
            'details' => ['required', 'string', 'max:5000'],
            'conflict_type' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['open', 'closed'])],
            'rescue_conducted' => ['nullable', 'boolean'],
            'species_rescued' => ['nullable', 'string', 'max:100'],
            'rehab_details' => ['nullable', 'string', 'max:2000'],
            'response_time' => ['nullable', 'date_format:H:i'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photos' => ['required', 'array', 'min:'.CaseEntry::MIN_PHOTOS, 'max:20'],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:15360'],
            // See PatrolEntryController::addIncident()'s `reported_at` for
            // why — same offline-sync purpose.
            'reported_at' => ['sometimes', 'date', 'before_or_equal:now'],
        ]);

        if (! empty($validated['client_id'])) {
            $existing = CaseEntryFiling::where('cef_case_id', $case->ce_id)
                ->where('cef_client_id', $validated['client_id'])
                ->first();

            if ($existing) {
                $existing->load('media');

                return response()->json([
                    'success' => true,
                    'message' => 'Case filed successfully.',
                    'data' => new CaseEntryFilingResource($existing),
                ], 201);
            }
        }

        $filing = DB::transaction(function () use ($validated, $case, $request) {
            $filing = CaseEntryFiling::create([
                'cef_case_id' => $case->ce_id,
                'cef_client_id' => $validated['client_id'] ?? null,
                'cef_reported_by' => $request->user()->u_id,
                'cef_filing_number' => $this->generateCaseNumber($case->range),
                'cef_details' => $validated['details'],
                'cef_status' => $validated['status'] ?? 'open',
                'cef_conflict_type' => $validated['conflict_type'] ?? null,
                'cef_rescue_conducted' => $validated['rescue_conducted'] ?? null,
                'cef_species_rescued' => $validated['species_rescued'] ?? null,
                'cef_rehab_details' => $validated['rehab_details'] ?? null,
                'cef_response_time' => $validated['response_time'] ?? null,
                'cef_latitude' => $validated['latitude'],
                'cef_longitude' => $validated['longitude'],
                'cef_reported_at' => isset($validated['reported_at'])
                    ? Carbon::parse($validated['reported_at'])->setTimezone(config('app.timezone'))
                    : now(),
            ]);

            foreach ($validated['photos'] as $photo) {
                $stored = $this->photos->compressAndStore($photo, 'case-filing-media/'.$case->ce_id);

                CaseEntryFilingMedia::create([
                    'cefm_filing_id' => $filing->cef_id,
                    'cefm_disk' => 'local',
                    'cefm_file_path' => $stored['path'],
                    'cefm_file_size' => $stored['size'],
                    'cefm_latitude' => $validated['latitude'],
                    'cefm_longitude' => $validated['longitude'],
                ]);
            }

            $case->ce_case_filed = true;
            $case->save();

            return $filing;
        });

        ReverseGeocodeLocation::dispatch(
            CaseEntryFiling::class,
            $filing->cef_id,
            'cef_id',
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            'cef_address'
        );

        $filing->load('media');

        return response()->json([
            'success' => true,
            'message' => 'Case filed successfully.',
            'data' => new CaseEntryFilingResource($filing),
        ], 201);
    }

    public function addNote(Request $request, CaseEntry $case)
    {
        $this->authorizeOwner($request, $case);
        $this->assertStarted($case);

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
        ]);

        $note = CaseEntryNote::create([
            'cen_case_id' => $case->ce_id,
            'cen_author_id' => $request->user()->u_id,
            'cen_text' => $validated['text'],
            'cen_created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Note recorded successfully.',
            'data' => new CaseEntryNoteResource($note),
        ], 201);
    }

    /** See `PatrolEntryController::addComment`'s identical doc comment. */
    public function addComment(Request $request, CaseEntry $case)
    {
        $user = $request->user();
        if (! $user->hasAppFeature('comment')) {
            abort(403, "You don't have permission to add comments.");
        }
        if (! $this->canViewEntry($request, $case)) {
            abort(403, 'You do not have access to this case.');
        }
        if ($case->ce_status !== CaseEntry::STATUS_COMPLETED) {
            abort(409, 'Comments can only be added once the case has closed.');
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
        ]);

        $comment = CaseEntryComment::create([
            'cec_case_id' => $case->ce_id,
            'cec_user_id' => $user->u_id,
            'cec_text' => $validated['text'],
            'cec_created_at' => now(),
        ]);
        $comment->setRelation('user', $user);

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully.',
            'data' => new CaseEntryCommentResource($comment),
        ], 201);
    }

    /** See `PatrolEntryController::updateComment`'s identical doc comment. */
    public function updateComment(Request $request, CaseEntry $case, CaseEntryComment $comment)
    {
        $user = $request->user();
        if (! $user->hasAppFeature('comment')) {
            abort(403, "You don't have permission to edit comments.");
        }
        if (! $this->canViewEntry($request, $case)) {
            abort(403, 'You do not have access to this case.');
        }
        if ($comment->cec_case_id !== $case->ce_id) {
            abort(404);
        }
        if ($comment->cec_user_id !== $user->u_id) {
            abort(403, 'You can only edit your own comments.');
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
        ]);

        $comment->update([
            'cec_text' => $validated['text'],
            'cec_updated_at' => now(),
        ]);
        $comment->setRelation('user', $user);

        return response()->json([
            'success' => true,
            'message' => 'Comment updated successfully.',
            'data' => new CaseEntryCommentResource($comment),
        ]);
    }

    public function updateIncidentStatus(Request $request, CaseEntry $case, CaseEntryIncident $incident)
    {
        $this->authorizeOwner($request, $case);

        if ($incident->cei_case_id !== $case->ce_id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'closed'])],
        ]);

        $incident->update(['cei_status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Incident status updated.',
            'data' => new CaseEntryIncidentResource($incident->fresh()),
        ]);
    }

    public function updateFilingStatus(Request $request, CaseEntry $case, CaseEntryFiling $filing)
    {
        $this->authorizeOwner($request, $case);

        if ($filing->cef_case_id !== $case->ce_id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'closed'])],
        ]);

        $filing->update(['cef_status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Case status updated.',
            'data' => new CaseEntryFilingResource($filing->fresh()),
        ]);
    }

    /**
     * Streams one incident photo — same ownership rule as every other
     * action here (the ranger must be this case's leader), reached through
     * the media row's own relation chain rather than trusting an id in the
     * URL, exactly like the admin panel's equivalent
     * {@see \App\Http\Controllers\Api\V1\AdminCaseEntryController::incidentMedia}.
     */
    public function incidentMedia(Request $request, CaseEntryIncidentMedia $media)
    {
        if (! $this->canViewEntry($request, $media->incident->case)) {
            abort(403, 'You do not have access to this case.');
        }

        return Storage::disk($media->ceim_disk)->response($media->ceim_file_path);
    }

    /**
     * Streams the selfie captured to start this case — see {@see startCase}.
     */
    public function startSelfie(Request $request, CaseEntry $case)
    {
        if (! $this->canViewEntry($request, $case)) {
            abort(403, 'You do not have access to this case.');
        }

        if ($case->ce_start_selfie_path === null) {
            abort(404);
        }

        return Storage::disk($case->ce_start_selfie_disk)->response($case->ce_start_selfie_path);
    }

    /**
     * Streams the selfie captured to close this case — see {@see closeCase}.
     */
    public function endSelfie(Request $request, CaseEntry $case)
    {
        if (! $this->canViewEntry($request, $case)) {
            abort(403, 'You do not have access to this case.');
        }

        if ($case->ce_end_selfie_path === null) {
            abort(404);
        }

        return Storage::disk($case->ce_end_selfie_disk)->response($case->ce_end_selfie_path);
    }

    /**
     * Streams one filing photo — see {@see incidentMedia}.
     */
    public function filingMedia(Request $request, CaseEntryFilingMedia $media)
    {
        if (! $this->canViewEntry($request, $media->filing->case)) {
            abort(403, 'You do not have access to this case.');
        }

        return Storage::disk($media->cefm_disk)->response($media->cefm_file_path);
    }

    /**
     * Streams one closing photo — see {@see incidentMedia}.
     */
    public function closingMedia(Request $request, CaseEntryClosingMedia $media)
    {
        if (! $this->canViewEntry($request, $media->case)) {
            abort(403, 'You do not have access to this case.');
        }

        return Storage::disk($media->cecm_disk)->response($media->cecm_file_path);
    }

    /**
     * Close the case: captures end time/GPS, finalizes vehicle odometer
     * readings, and requires both a closing report (unlike
     * {@see PatrolEntryController::endPatrol}'s optional one — a case
     * closure must always explain the outcome) and a selfie proving the
     * ranger themself is the one closing it, same as the mandatory start
     * selfie ({@see startCase}). No longer takes bulk evidence photos —
     * those belong on the individual incidents/filings within the case
     * ({@see addIncident}/{@see addFiling}), not the close action itself.
     */
    public function closeCase(Request $request, CaseEntry $case)
    {
        $this->authorizeOwner($request, $case);
        $this->assertInProgress($case);

        $validated = $request->validate([
            'end_latitude' => ['required', 'numeric', 'between:-90,90'],
            'end_longitude' => ['required', 'numeric', 'between:-180,180'],
            'report' => ['required', 'string', 'max:5000'],
            'vehicle_odometers' => ['sometimes', 'array'],
            'vehicle_odometers.*.cev_id' => ['required_with:vehicle_odometers', 'uuid'],
            'vehicle_odometers.*.end_odometer' => ['required_with:vehicle_odometers', 'numeric', 'min:0', 'max:9999999.99'],
            'selfie' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:15360'],
            // See PatrolEntryController::endPatrol()'s `ended_at` for why —
            // same offline-sync purpose.
            'ended_at' => ['sometimes', 'date', 'before_or_equal:now'],
        ]);

        $storedSelfie = $this->photos->compressAndStore($validated['selfie'], 'case-end-selfies/'.$case->ce_id);

        $caseVehicles = $case->vehicles()->get()->keyBy('cev_id');

        foreach ($validated['vehicle_odometers'] ?? [] as $reading) {
            $vehicle = $caseVehicles->get($reading['cev_id']);

            if (! $vehicle) {
                throw ValidationException::withMessages([
                    'vehicle_odometers' => "Vehicle {$reading['cev_id']} does not belong to this case.",
                ]);
            }

            if ($vehicle->cev_start_odometer !== null && $reading['end_odometer'] < $vehicle->cev_start_odometer) {
                throw ValidationException::withMessages([
                    'vehicle_odometers' => "Ending odometer for vehicle {$reading['cev_id']} cannot be less than the starting reading.",
                ]);
            }
        }

        $case = DB::transaction(function () use ($case, $validated, $storedSelfie) {
            foreach ($validated['vehicle_odometers'] ?? [] as $reading) {
                CaseEntryVehicle::where('cev_id', $reading['cev_id'])
                    ->update(['cev_end_odometer' => $reading['end_odometer']]);
            }

            $totalDistance = $case->vehicles()->sum('cev_distance');
            // See PatrolEntryController::endPatrol's comment — same naive-column
            // timezone trap, and `ce_end_time` derives from this same value.
            $endedAt = isset($validated['ended_at'])
                ? Carbon::parse($validated['ended_at'])->setTimezone(config('app.timezone'))
                : now();

            $case->update([
                'ce_end_time' => $endedAt->format('H:i:s'),
                'ce_end_latitude' => $validated['end_latitude'],
                'ce_end_longitude' => $validated['end_longitude'],
                'ce_report' => $validated['report'],
                'ce_total_distance' => $totalDistance,
                'ce_end_selfie_disk' => 'local',
                'ce_end_selfie_path' => $storedSelfie['path'],
                'ce_ended_at' => $endedAt,
                'ce_status' => CaseEntry::STATUS_COMPLETED,
            ]);

            return $case;
        });

        ReverseGeocodeLocation::dispatch(
            CaseEntry::class,
            $case->ce_id,
            'ce_id',
            (float) $validated['end_latitude'],
            (float) $validated['end_longitude'],
            'ce_end_address'
        );

        $case->load(['range', 'beat', 'modes', 'vehicles.vehicle', 'incidents.media', 'filings.media', 'closingMedia']);

        return response()->json([
            'success' => true,
            'message' => 'Case closed successfully.',
            'data' => new CaseEntryResource($case),
        ]);
    }

    private function authorizeOwner(Request $request, CaseEntry $case): void
    {
        if ($case->ce_leader_id !== $request->user()->u_id) {
            abort(403, 'You are not the leader for this case.');
        }
    }

    /** See `PatrolEntryController::canViewEntry`'s identical doc comment. */
    private function canViewEntry(Request $request, CaseEntry $case): bool
    {
        $user = $request->user();
        if ($case->ce_leader_id === $user->u_id) {
            return true;
        }

        return $user->ranges()->where('ranges.rn_id', $case->ce_range_id)->exists();
    }

    private function assertInProgress(CaseEntry $case): void
    {
        // Distinct message from the "already ended" case below is load-bearing:
        // the app's offline sync (CaseSyncQueueService._isStalePingAfterCaseClosed)
        // treats a `close_case` row's "already ended" 409 as proof this same close
        // already landed on an earlier attempt (lost response) and safely refreshes
        // from the server — that assumption only holds when the case is genuinely
        // `completed`. Reusing the same message for a still-`pending` case (never
        // actually started server-side) made the client wrongly assume the close
        // succeeded, overwrite its local cache with the server's real `pending`
        // status, and drop the close data for good.
        if ($case->ce_status === CaseEntry::STATUS_PENDING) {
            abort(409, 'Start this case before ending it.');
        }
        if ($case->ce_status !== CaseEntry::STATUS_IN_PROGRESS) {
            abort(409, 'This case has already ended.');
        }
    }

    private function assertNotCompleted(CaseEntry $case): void
    {
        if ($case->ce_status === CaseEntry::STATUS_COMPLETED) {
            abort(409, 'This case has already ended.');
        }
    }

    /**
     * See {@see PatrolEntryController::assertStarted} for the full
     * reasoning — same fix, same shape, mirrored here for the Case module.
     */
    private function assertStarted(CaseEntry $case): void
    {
        if ($case->ce_status === CaseEntry::STATUS_PENDING) {
            abort(409, 'Start this case before adding data to it.');
        }
    }

    /**
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
     * Issues the next case number for the current year (e.g.
     * `CASE-SR-2026-00042`) from {@see CaseEntryNumberSequence}, locking the
     * row so concurrent submissions never collide — same pattern as
     * {@see PatrolEntryController::generatePatrolId}.
     */
    private function generateCaseNumber(Ranges $range): string
    {
        $rangeCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $range->rn_range_id));
        $year = (int) now()->year;

        CaseEntryNumberSequence::firstOrCreate(['cens_year' => $year], ['cens_last_number' => 0]);

        $sequence = CaseEntryNumberSequence::where('cens_year', $year)->lockForUpdate()->first();
        $sequence->increment('cens_last_number');

        return sprintf('CASE-%s-%d-%05d', $rangeCode, $year, $sequence->cens_last_number);
    }
}
