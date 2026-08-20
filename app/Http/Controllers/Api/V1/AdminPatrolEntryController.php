<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminPatrolEntryResource;
use App\Http\Resources\PatrolRoutePointResource;
use App\Models\PatrolCaseMedia;
use App\Models\PatrolIncidentMedia;
use App\Models\PatrollingEntries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Read-only visibility into every ranger's patrols, for the admin panel's
 * "Patrollings" section — unlike {@see \App\Http\Controllers\Api\V1\PatrolEntryController},
 * which is scoped to the field app's own authenticated ranger.
 */
class AdminPatrolEntryController extends Controller
{
    /**
     * All patrol entries, in-progress first, newest first — optionally
     * filtered by status and/or range.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'completed'])],
            'range_id' => ['sometimes', 'uuid', 'exists:ranges,rn_id'],
        ]);

        $entries = PatrollingEntries::query()
            ->with([
                'range', 'beat', 'patrolType', 'modes', 'vehicles.vehicle',
                'patrolLeader.details', 'caseReports.media', 'incidents.media',
            ])
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('pe_status', $status)
            )
            ->when(
                $validated['range_id'] ?? null,
                fn ($query, $rangeId) => $query->where('pe_range_id', $rangeId)
            )
            ->orderByRaw("CASE pe_status WHEN 'in_progress' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->latest('pe_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Patrol entries retrieved successfully.',
            'data' => AdminPatrolEntryResource::collection($entries),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
                'last_page' => $entries->lastPage(),
            ],
        ]);
    }

    public function show(PatrollingEntries $entry)
    {
        $entry->load([
            'range', 'beat', 'patrolType', 'modes', 'vehicles.vehicle',
            'patrolLeader.details', 'caseReports.media', 'incidents.media',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Patrol entry retrieved successfully.',
            'data' => new AdminPatrolEntryResource($entry),
        ]);
    }

    /**
     * The entry's GPS trail, oldest first. Pass `since` (an ISO timestamp)
     * to fetch only points recorded after it — what the live-tracking map
     * polls on an interval instead of re-fetching the whole trail.
     */
    public function routePoints(Request $request, PatrollingEntries $entry)
    {
        $validated = $request->validate([
            'since' => ['sometimes', 'date'],
        ]);

        $points = $entry->routePoints()
            ->with('vehicle')
            ->when(
                $validated['since'] ?? null,
                fn ($query, $since) => $query->where('prp_recorded_at', '>', $since)
            )
            ->orderBy('prp_recorded_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Route points retrieved successfully.',
            'data' => PatrolRoutePointResource::collection($points),
        ]);
    }

    /**
     * Streams a case-report photo. Photos are stored privately (not on a
     * publicly-served disk), so the admin panel proxies this through its
     * own server-side route rather than linking to it directly.
     */
    public function caseMedia(PatrolCaseMedia $media)
    {
        return Storage::disk($media->pcm_disk)->response($media->pcm_file_path);
    }

    /**
     * Streams an incident photo — see {@see caseMedia}.
     */
    public function incidentMedia(PatrolIncidentMedia $media)
    {
        return Storage::disk($media->pim_disk)->response($media->pim_file_path);
    }
}
