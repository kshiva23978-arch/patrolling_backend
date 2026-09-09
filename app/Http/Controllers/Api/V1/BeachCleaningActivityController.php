<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeachCleaningActivityResource;
use App\Models\Beach;
use App\Models\BeachCleaningActivity;
use App\Models\BeachCleaningMedia;
use App\Models\BeachCleaningSegregation;
use App\Services\PatrolPhotoService;
use App\Services\UnfinishedWorkChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * A ranger's beach-cleaning drive, walked through in 5 steps by the app:
 * create (this controller's {@see store}, live `in_progress` immediately),
 * details, collection, and segregation (each its own PATCH/POST below), then
 * {@see submit}. See the `beach_cleaning_activities` migration's doc comment
 * for why this is a dedicated module rather than another `activities`
 * category.
 */
class BeachCleaningActivityController extends Controller
{
    private const WITH = ['destination', 'beach', 'media', 'segregations.country', 'segregations.wasteCategory'];

    public function __construct(
        private readonly PatrolPhotoService $photos,
        private readonly UnfinishedWorkChecker $unfinishedWork,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $activities = BeachCleaningActivity::query()
            ->where('bca_created_by', $user->u_id)
            ->with(self::WITH)
            ->latest('bca_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Beach cleaning activities retrieved successfully.',
            'data' => BeachCleaningActivityResource::collection($activities),
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
        $this->authorizeOwner($request, $beachCleaningActivity);
        $beachCleaningActivity->load(self::WITH);

        return response()->json([
            'success' => true,
            'message' => 'Beach cleaning activity retrieved successfully.',
            'data' => new BeachCleaningActivityResource($beachCleaningActivity),
        ]);
    }

    /** Step 1 — create. Live immediately, no separate "start" step. */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->hasAppFeature('beach_cleaning')) {
            abort(403, "You don't have permission to create beach cleaning activities.");
        }

        $validated = $request->validate([
            // Optional client-generated id — same offline-create idempotency
            // as Activity::store; see there for the full reasoning.
            'bca_id' => ['sometimes', 'uuid'],
            'activity_name' => ['required', 'string', 'max:150'],
            'officer_name' => ['required', 'string', 'max:150'],
            'destination_id' => ['required', 'uuid', 'exists:destinations,ds_id'],
            'beach_id' => ['nullable', 'uuid', 'exists:beaches,bc_id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $this->assertBeachBelongsToDestination($validated);

        if (! empty($validated['bca_id'])) {
            $existing = $this->findByClientId($validated['bca_id'], $user->u_id);
            if ($existing) {
                return $this->response($existing, 'Beach cleaning activity created successfully.');
            }
        }

        $tokenId = $user->currentAccessToken()?->id;

        if ($this->unfinishedWork->hasInProgressBeachCleaning($user->u_id, $tokenId)) {
            abort(409, 'You already have a beach cleaning drive that has not ended yet. End it before starting a new one.');
        }

        $activity = BeachCleaningActivity::create([
            'bca_id' => $validated['bca_id'] ?? null,
            'bca_activity_name' => $validated['activity_name'],
            'bca_officer_name' => $validated['officer_name'],
            'bca_destination_id' => $validated['destination_id'],
            'bca_beach_id' => $validated['beach_id'] ?? null,
            'bca_latitude' => $validated['latitude'] ?? null,
            'bca_longitude' => $validated['longitude'] ?? null,
            'bca_created_by' => $user->u_id,
            'bca_created_via_token_id' => $tokenId,
            'bca_status' => BeachCleaningActivity::STATUS_IN_PROGRESS,
        ]);

        return $this->response($activity, 'Beach cleaning activity created successfully.');
    }

    /** Step 2 — cleaning details. */
    public function updateDetails(Request $request, BeachCleaningActivity $beachCleaningActivity)
    {
        $this->authorizeOwner($request, $beachCleaningActivity);
        $this->assertInProgress($beachCleaningActivity);

        $validated = $request->validate([
            'summary' => ['nullable', 'string', 'max:5000'],
            'participant_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $beachCleaningActivity->update([
            'bca_summary' => $validated['summary'] ?? null,
            'bca_participant_count' => $validated['participant_count'] ?? null,
        ]);

        return $this->response($beachCleaningActivity, 'Details saved successfully.');
    }

    /** Step 3 — collection totals. */
    public function updateCollection(Request $request, BeachCleaningActivity $beachCleaningActivity)
    {
        $this->authorizeOwner($request, $beachCleaningActivity);
        $this->assertInProgress($beachCleaningActivity);

        $validated = $request->validate([
            'bags_collected' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'total_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $beachCleaningActivity->update([
            'bca_bags_collected' => $validated['bags_collected'] ?? null,
            'bca_total_weight_kg' => $validated['total_weight_kg'] ?? null,
        ]);

        return $this->response($beachCleaningActivity, 'Collection details saved successfully.');
    }

    /** Step 4 — the overall segregation percentage (the line items below are separate). */
    public function updateSegregationPercent(Request $request, BeachCleaningActivity $beachCleaningActivity)
    {
        $this->authorizeOwner($request, $beachCleaningActivity);
        $this->assertInProgress($beachCleaningActivity);

        $validated = $request->validate([
            'segregation_percent' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $beachCleaningActivity->update(['bca_segregation_percent' => $validated['segregation_percent'] ?? null]);

        return $this->response($beachCleaningActivity, 'Segregation percentage saved successfully.');
    }

    /** Step 4 — "+ Add More": one country/waste-category/quantity row. */
    public function addSegregation(Request $request, BeachCleaningActivity $beachCleaningActivity)
    {
        $this->authorizeOwner($request, $beachCleaningActivity);
        $this->assertInProgress($beachCleaningActivity);

        $validated = $request->validate([
            'bcs_id' => ['sometimes', 'uuid'],
            'country_id' => ['required', 'uuid', 'exists:countries,co_id'],
            'waste_category_id' => ['required', 'uuid', 'exists:waste_categories,wc_id'],
            'quantity_kg' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            // The ranger's own weighing for this row — never derived from
            // quantity_kg (a plain count, despite its column's name; see
            // that migration's doc comment).
            'weight_kg' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
        ]);

        if (! empty($validated['bcs_id'])) {
            $existing = BeachCleaningSegregation::where('bcs_id', $validated['bcs_id'])
                ->where('bcs_activity_id', $beachCleaningActivity->bca_id)
                ->first();
            if ($existing) {
                return $this->response($beachCleaningActivity, 'Segregation row added successfully.');
            }
        }

        BeachCleaningSegregation::create([
            'bcs_id' => $validated['bcs_id'] ?? null,
            'bcs_activity_id' => $beachCleaningActivity->bca_id,
            'bcs_country_id' => $validated['country_id'],
            'bcs_waste_category_id' => $validated['waste_category_id'],
            'bcs_quantity_kg' => $validated['quantity_kg'],
            'bcs_weight_kg' => $validated['weight_kg'] ?? null,
        ]);

        return $this->response($beachCleaningActivity, 'Segregation row added successfully.');
    }

    public function removeSegregation(Request $request, BeachCleaningActivity $beachCleaningActivity, BeachCleaningSegregation $segregation)
    {
        $this->authorizeOwner($request, $beachCleaningActivity);
        $this->assertInProgress($beachCleaningActivity);

        if ($segregation->bcs_activity_id !== $beachCleaningActivity->bca_id) {
            abort(404);
        }

        $segregation->delete();

        return $this->response($beachCleaningActivity, 'Segregation row removed successfully.');
    }

    /**
     * A geo-tagged photo for one of the three capture points (before/other
     * in step 2, collection in step 3) — see {@see BeachCleaningMedia::KINDS}.
     */
    public function addMedia(Request $request, BeachCleaningActivity $beachCleaningActivity)
    {
        $this->authorizeOwner($request, $beachCleaningActivity);
        $this->assertInProgress($beachCleaningActivity);

        $validated = $request->validate([
            'bcm_id' => ['sometimes', 'uuid'],
            'kind' => ['required', 'string', 'in:'.implode(',', BeachCleaningMedia::KINDS)],
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:15360'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        if (! empty($validated['bcm_id'])) {
            $existing = BeachCleaningMedia::where('bcm_id', $validated['bcm_id'])
                ->where('bcm_activity_id', $beachCleaningActivity->bca_id)
                ->first();
            if ($existing) {
                return $this->mediaResponse($existing);
            }
        }

        $stored = $this->photos->compressAndStore($request->file('photo'), 'beach-cleaning-media/'.$beachCleaningActivity->bca_id);

        $media = BeachCleaningMedia::create([
            'bcm_id' => $validated['bcm_id'] ?? null,
            'bcm_activity_id' => $beachCleaningActivity->bca_id,
            'bcm_kind' => $validated['kind'],
            'bcm_disk' => 'local',
            'bcm_file_path' => $stored['path'],
            'bcm_file_size' => $stored['size'],
            'bcm_latitude' => $validated['latitude'] ?? null,
            'bcm_longitude' => $validated['longitude'] ?? null,
        ]);

        return $this->mediaResponse($media);
    }

    public function media(Request $request, BeachCleaningMedia $media)
    {
        $this->authorizeOwner($request, $media->activity);

        return Storage::disk($media->bcm_disk)->response($media->bcm_file_path);
    }

    /**
     * Removes one photo the ranger captured in error — the file is deleted
     * from disk first (it doesn't cascade automatically the way the DB row
     * does), then the row itself.
     */
    public function removeMedia(Request $request, BeachCleaningActivity $beachCleaningActivity, BeachCleaningMedia $media)
    {
        $this->authorizeOwner($request, $beachCleaningActivity);
        $this->assertInProgress($beachCleaningActivity);

        if ($media->bcm_activity_id !== $beachCleaningActivity->bca_id) {
            abort(404);
        }

        Storage::disk($media->bcm_disk)->delete($media->bcm_file_path);
        $media->delete();

        return $this->response($beachCleaningActivity, 'Photo removed successfully.');
    }

    /**
     * Step 5 — finalizes the activity. Idempotent, same reasoning as
     * Activity::end: a queued `submit` sync retried after its first attempt
     * actually succeeded should report the already-submitted activity back
     * as success rather than 409 forever.
     */
    public function submit(Request $request, BeachCleaningActivity $beachCleaningActivity)
    {
        $this->authorizeOwner($request, $beachCleaningActivity);

        if ($beachCleaningActivity->bca_status === BeachCleaningActivity::STATUS_SUBMITTED) {
            return $this->response($beachCleaningActivity, 'Beach cleaning activity submitted successfully.');
        }

        $this->assertInProgress($beachCleaningActivity);

        $validated = $request->validate([
            'report' => ['nullable', 'string', 'max:5000'],
        ]);

        $beachCleaningActivity->update([
            'bca_closing_report' => $validated['report'] ?? null,
            'bca_status' => BeachCleaningActivity::STATUS_SUBMITTED,
            'bca_submitted_at' => now(),
        ]);

        return $this->response($beachCleaningActivity, 'Beach cleaning activity submitted successfully.');
    }

    private function assertBeachBelongsToDestination(array $validated): void
    {
        if (empty($validated['beach_id'])) {
            return;
        }

        if (! Beach::where('bc_id', $validated['beach_id'])->where('bc_destination_id', $validated['destination_id'])->exists()) {
            abort(422, 'This beach does not belong to the selected destination.');
        }
    }

    private function findByClientId(string $id, string $userId): ?BeachCleaningActivity
    {
        return BeachCleaningActivity::where('bca_id', $id)->where('bca_created_by', $userId)->first();
    }

    private function response(BeachCleaningActivity $activity, string $message)
    {
        $activity->loadMissing(self::WITH);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new BeachCleaningActivityResource($activity),
        ]);
    }

    private function mediaResponse(BeachCleaningMedia $media)
    {
        return response()->json([
            'success' => true,
            'message' => 'Photo added successfully.',
            'data' => [
                'id' => $media->bcm_id,
                'kind' => $media->bcm_kind,
                'url' => route('app.beach-cleaning-media', $media->bcm_id),
                'file_size' => $media->bcm_file_size,
            ],
        ], 201);
    }

    private function authorizeOwner(Request $request, BeachCleaningActivity $activity): void
    {
        if ($activity->bca_created_by !== $request->user()->u_id) {
            abort(403, 'You are not the owner of this beach cleaning activity.');
        }
    }

    private function assertInProgress(BeachCleaningActivity $activity): void
    {
        if ($activity->bca_status !== BeachCleaningActivity::STATUS_IN_PROGRESS) {
            abort(409, 'This beach cleaning activity has already been submitted.');
        }
    }
}
