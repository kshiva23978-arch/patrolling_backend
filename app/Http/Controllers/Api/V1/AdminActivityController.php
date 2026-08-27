<?php

namespace App\Http\Controllers\Api\V1;

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
 * single creator.
 */
class AdminActivityController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in([Activity::STATUS_IN_PROGRESS, Activity::STATUS_COMPLETED])],
        ]);

        $activities = Activity::query()
            ->with(['createdBy.details', 'participants', 'media'])
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('act_status', $status)
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

    public function show(Activity $activity)
    {
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
    public function media(ActivityMedia $media)
    {
        return Storage::disk($media->acm_disk)->response($media->acm_file_path);
    }
}
