<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ScopesToRanges;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminCaseEntryResource;
use App\Http\Resources\CaseEntryRoutePointResource;
use App\Models\CaseEntry;
use App\Models\CaseEntryClosingMedia;
use App\Models\CaseEntryFilingMedia;
use App\Models\CaseEntryIncidentMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Read-only visibility into every ranger's standalone Cases, for the admin
 * panel's "Cases" section — unlike {@see \App\Http\Controllers\Api\V1\CaseEntryController},
 * which is scoped to the field app's own authenticated ranger. Mirrors
 * {@see AdminPatrolEntryController} for the Patrol module.
 */
class AdminCaseEntryController extends Controller
{
    use ScopesToRanges;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in([CaseEntry::STATUS_PENDING, CaseEntry::STATUS_IN_PROGRESS, CaseEntry::STATUS_COMPLETED])],
            'range_id' => ['sometimes', 'uuid', 'exists:ranges,rn_id'],
        ]);

        if (isset($validated['range_id'])) {
            $this->assertRangeAccessible($request, $validated['range_id']);
        }

        $query = CaseEntry::query()
            ->with(['range', 'beat', 'modes', 'vehicles.vehicle', 'leader.details', 'incidents.media', 'filings.media'])
            ->when(
                $validated['status'] ?? null,
                fn ($q, $status) => $q->where('ce_status', $status)
            )
            ->when(
                $validated['range_id'] ?? null,
                fn ($q, $rangeId) => $q->where('ce_range_id', $rangeId)
            );
        $query = $this->scopeToAccessibleRanges($query, $request, 'ce_range_id');

        $cases = $query
            ->orderByRaw("CASE ce_status WHEN 'in_progress' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->latest('ce_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Cases retrieved successfully.',
            'data' => AdminCaseEntryResource::collection($cases),
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
        $this->assertRangeAccessible($request, $case->ce_range_id);

        $case->load(['range', 'beat', 'modes', 'vehicles.vehicle', 'leader.details', 'incidents.media', 'filings.media', 'notes', 'closingMedia']);

        return response()->json([
            'success' => true,
            'message' => 'Case retrieved successfully.',
            'data' => new AdminCaseEntryResource($case),
        ]);
    }

    /**
     * The case's GPS trail, oldest first. Pass `since` (an ISO timestamp,
     * typically the last point already held client-side) to fetch only
     * newer points — what the admin panel's live-tracking map polls, same
     * pattern as {@see AdminPatrolEntryController::routePoints}.
     */
    public function routePoints(Request $request, CaseEntry $case)
    {
        $this->assertRangeAccessible($request, $case->ce_range_id);

        $validated = $request->validate([
            'since' => ['sometimes', 'date'],
        ]);

        $points = $case->routePoints()
            ->with('vehicle')
            ->when(
                $validated['since'] ?? null,
                fn ($query, $since) => $query->where('cerp_recorded_at', '>', $since)
            )
            ->orderBy('cerp_recorded_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Route points retrieved successfully.',
            'data' => CaseEntryRoutePointResource::collection($points),
        ]);
    }

    /**
     * Deletes a case outright — its incidents, filings, route points,
     * vehicles, and notes all cascade at the DB level (see the
     * `case_entries`-referencing FKs' `cascadeOnDelete()`), but their photo
     * files on disk don't, so those are removed explicitly first to avoid
     * leaving them orphaned. Mirrors {@see AdminPatrolEntryController::destroy}.
     */
    public function destroy(Request $request, CaseEntry $case)
    {
        $this->assertRangeAccessible($request, $case->ce_range_id);

        $case->load(['incidents.media', 'filings.media', 'closingMedia']);

        foreach ($case->incidents as $incident) {
            foreach ($incident->media as $media) {
                Storage::disk($media->ceim_disk)->delete($media->ceim_file_path);
            }
        }
        foreach ($case->filings as $filing) {
            foreach ($filing->media as $media) {
                Storage::disk($media->cefm_disk)->delete($media->cefm_file_path);
            }
        }
        foreach ($case->closingMedia as $media) {
            Storage::disk($media->cecm_disk)->delete($media->cecm_file_path);
        }

        $case->delete();

        return response()->json([
            'success' => true,
            'message' => 'Case deleted successfully.',
            'data' => null,
        ]);
    }

    /** Streams an incident photo — see {@see AdminPatrolEntryController::caseMedia} for the same pattern. */
    public function incidentMedia(Request $request, CaseEntryIncidentMedia $media)
    {
        $this->assertRangeAccessible($request, $media->incident->case->ce_range_id);

        return Storage::disk($media->ceim_disk)->response($media->ceim_file_path);
    }

    /** Streams the selfie the ranger captured to start this case. */
    public function startSelfie(Request $request, CaseEntry $case)
    {
        $this->assertRangeAccessible($request, $case->ce_range_id);

        if ($case->ce_start_selfie_path === null) {
            abort(404);
        }

        return Storage::disk($case->ce_start_selfie_disk)->response($case->ce_start_selfie_path);
    }

    /** Streams a case-filing photo. */
    public function filingMedia(Request $request, CaseEntryFilingMedia $media)
    {
        $this->assertRangeAccessible($request, $media->filing->case->ce_range_id);

        return Storage::disk($media->cefm_disk)->response($media->cefm_file_path);
    }

    /** Streams a close-case photo. */
    public function closingMedia(Request $request, CaseEntryClosingMedia $media)
    {
        $this->assertRangeAccessible($request, $media->case->ce_range_id);

        return Storage::disk($media->cecm_disk)->response($media->cecm_file_path);
    }
}
