<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ScopesToRanges;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminActivityResource;
use App\Models\Activity;
use App\Models\ActivityMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Read-only admin view of every ranger's field activities — unlike
 * {@see ActivityController} (the app-facing one), this isn't scoped to a
 * single creator. Activities have no range column of their own (see the
 * `create_activities_table` migration), so a scoped admin's visibility is
 * derived from the *creator's* assigned ranges instead.
 */
class AdminActivityController extends Controller
{
    use ScopesToRanges;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in([Activity::STATUS_IN_PROGRESS, Activity::STATUS_COMPLETED])],
        ]);

        $rangeIds = $this->accessibleRangeIds($request);

        $activities = Activity::query()
            ->with(['createdBy.details', 'participants', 'media'])
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('act_status', $status)
            )
            ->when(
                $rangeIds !== null,
                fn ($query) => $query->whereHas('createdBy.ranges', fn ($q) => $q->whereIn('rn_id', $rangeIds))
            )
            ->latest('act_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Activities retrieved successfully.',
            'data' => AdminActivityResource::collection($activities),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
                'last_page' => $activities->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Activity $activity)
    {
        $this->assertActivityAccessible($request, $activity);

        $activity->load(['createdBy.details', 'participants', 'media']);

        return response()->json([
            'success' => true,
            'message' => 'Activity retrieved successfully.',
            'data' => new AdminActivityResource($activity),
        ]);
    }

    /**
     * Streams an activity photo — see
     * {@see AdminPatrolEntryController::caseMedia} for the same pattern.
     */
    public function media(Request $request, ActivityMedia $media)
    {
        $this->assertActivityAccessible($request, $media->activity);

        return Storage::disk($media->acm_disk)->response($media->acm_file_path);
    }

    /** Aborts with a 403 unless [$activity]'s creator shares a range with [$request]'s admin. */
    private function assertActivityAccessible(Request $request, Activity $activity): void
    {
        $rangeIds = $this->accessibleRangeIds($request);
        if ($rangeIds === null) {
            return;
        }

        $creatorRangeIds = $activity->createdBy?->ranges()->pluck('rn_id')->all() ?? [];
        if (array_intersect($creatorRangeIds, $rangeIds) === []) {
            abort(403, 'You do not have access to this activity.');
        }
    }
}
