<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ScopesToDestinations;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminBeachCleaningActivityResource;
use App\Models\BeachCleaningActivity;
use App\Models\BeachCleaningMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Read-only admin view of every ranger's beach cleaning drives — scoped by
 * the activity's own `bca_destination_id` (unlike Activities, which have no
 * destination column of their own and are scoped through the creator's
 * assigned ranges instead).
 */
class AdminBeachCleaningActivityController extends Controller
{
    use ScopesToDestinations;

    private const WITH = [
        'destination', 'beach', 'createdBy.details', 'media',
        'segregations.country', 'segregations.wasteCategory',
        'categoryWeights.wasteCategory',
    ];

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in([BeachCleaningActivity::STATUS_IN_PROGRESS, BeachCleaningActivity::STATUS_SUBMITTED])],
            'destination_id' => ['sometimes', 'uuid'],
        ]);

        $activities = BeachCleaningActivity::query()
            ->with(self::WITH)
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('bca_status', $status))
            ->when($validated['destination_id'] ?? null, fn ($q, $id) => $q->where('bca_destination_id', $id))
            ->tap(fn ($q) => $this->scopeToAccessibleRanges($q, $request, 'bca_destination_id'))
            ->latest('bca_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Beach cleaning activities retrieved successfully.',
            'data' => AdminBeachCleaningActivityResource::collection($activities),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
                'last_page' => $activities->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, BeachCleaningActivity $beachCleaningActivity)
    {
        $this->assetDestinationAccessible($request, $beachCleaningActivity->bca_destination_id);
        $beachCleaningActivity->load(self::WITH);

        return response()->json([
            'success' => true,
            'message' => 'Beach cleaning activity retrieved successfully.',
            'data' => new AdminBeachCleaningActivityResource($beachCleaningActivity),
        ]);
    }

    public function media(Request $request, BeachCleaningMedia $media)
    {
        $this->assetDestinationAccessible($request, $media->activity->bca_destination_id);

        return Storage::disk($media->bcm_disk)->response($media->bcm_file_path);
    }

    /**
     * Deletes a beach cleaning activity outright — its media and
     * segregation rows cascade at the DB level, but photo files on disk
     * don't, so those are removed explicitly first (same pattern as
     * AdminActivityController::destroy).
     */
    public function destroy(Request $request, BeachCleaningActivity $beachCleaningActivity)
    {
        $this->assetDestinationAccessible($request, $beachCleaningActivity->bca_destination_id);

        $beachCleaningActivity->load('media');

        foreach ($beachCleaningActivity->media as $media) {
            Storage::disk($media->bcm_disk)->delete($media->bcm_file_path);
        }

        $beachCleaningActivity->delete();

        return response()->json([
            'success' => true,
            'message' => 'Beach cleaning activity deleted successfully.',
            'data' => null,
        ]);
    }
}
