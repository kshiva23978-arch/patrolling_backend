<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Jobs\ReverseGeocodeLocation;
use App\Models\Activity;
use App\Models\ActivityMedia;
use App\Models\ActivityParticipant;
use App\Services\PatrolPhotoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Standalone field activities (surveys, awareness drives, plantation days,
 * etc.) — deliberately independent of the patrol/case flow. A ranger
 * creates one (name, description, GPS location, who conducted it), it's
 * `in_progress` immediately, optionally gathers participants and captioned
 * photos while active, and is closed out with a report/conclusion.
 */
class ActivityController extends Controller
{
    public function __construct(private readonly PatrolPhotoService $photos) {}

    /**
     * This ranger's own activities, most recent first.
     */
    public function index(Request $request)
    {
        $activities = Activity::where('act_created_by', $request->user()->u_id)
            ->with(['participants', 'media'])
            ->latest('act_created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Activities retrieved successfully.',
            'data' => ActivityResource::collection($activities),
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
        $this->authorizeOwner($request, $activity);
        $activity->load(['participants', 'media']);

        return response()->json([
            'success' => true,
            'message' => 'Activity retrieved successfully.',
            'data' => new ActivityResource($activity),
        ]);
    }

    /**
     * Creates the activity — it's live (`in_progress`) immediately, no
     * separate "start" step the way a patrol has.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->hasAppFeature('activity')) {
            abort(403, "You don't have permission to create activities.");
        }

        $validated = $request->validate([
            // Optional client-generated id: the app can create an activity
            // while offline and start using it locally (participants,
            // photos, ending it) before this request ever reaches the
            // server. Sending the same id back here — instead of always
            // minting a fresh one — means the local record and the server
            // record are the same row from the start, and also makes a
            // retried request (network dropped after the first attempt
            // actually succeeded) idempotent rather than a duplicate-create
            // error; see the reuse below. Mirrors PatrolEntryController's
            // `pe_id` handling.
            'act_id' => ['sometimes', 'uuid'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'conducted_by' => ['required', 'string', 'max:150'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        if (! empty($validated['act_id'])) {
            $existing = $this->findActivityByClientId($validated['act_id'], $user->u_id);
            if ($existing) {
                return $this->activityResponse($existing, 'Activity created successfully.');
            }
        }

        try {
            $activity = Activity::create([
                'act_id' => $validated['act_id'] ?? null,
                'act_name' => $validated['name'],
                'act_description' => $validated['description'] ?? null,
                'act_conducted_by' => $validated['conducted_by'],
                'act_created_by' => $user->u_id,
                'act_status' => Activity::STATUS_IN_PROGRESS,
                'act_latitude' => $validated['latitude'] ?? null,
                'act_longitude' => $validated['longitude'] ?? null,
                'act_started_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Lost a create race against another sync attempt for the same
            // client-minted id — e.g. the app fires an opportunistic sync
            // after every offline write, so creating an activity and adding
            // a participant/photo to it in quick succession while online can
            // launch two overlapping syncs that both see the `create_activity`
            // row as still-pending and both submit it. The request that lost
            // the race is exactly as correct as the one that won — the row
            // it's looking for now exists, just not created by *this*
            // request — so recover instead of surfacing a 500.
            if (empty($validated['act_id']) || ! $this->isUniqueViolation($e)) {
                throw $e;
            }
            $existing = $this->findActivityByClientId($validated['act_id'], $user->u_id);
            if (! $existing) {
                throw $e;
            }

            return $this->activityResponse($existing, 'Activity created successfully.');
        }

        if (isset($validated['latitude'], $validated['longitude'])) {
            ReverseGeocodeLocation::dispatch(
                Activity::class,
                $activity->act_id,
                'act_id',
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                'act_address'
            );
        }

        return $this->activityResponse($activity, 'Activity created successfully.');
    }

    private function findActivityByClientId(string $actId, string $userId): ?Activity
    {
        return Activity::where('act_id', $actId)
            ->where('act_created_by', $userId)
            ->first();
    }

    private function activityResponse(Activity $activity, string $message): JsonResponse
    {
        $activity->loadMissing(['participants', 'media']);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new ActivityResource($activity),
        ], 201);
    }

    /**
     * `true` for a Postgres unique-constraint violation (SQLSTATE 23505) —
     * the only `QueryException` case the client-id race conditions above
     * should recover from; anything else (a real constraint violation
     * elsewhere, a connection failure) should still surface as a 500.
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23505';
    }

    /**
     * Adds one participant by name — optional, any number, any time while
     * the activity is still active.
     */
    public function addParticipant(Request $request, Activity $activity)
    {
        $this->authorizeOwner($request, $activity);
        $this->assertInProgress($activity);

        $validated = $request->validate([
            // Same client-id idempotency as Activity::store — a participant
            // added offline is queued locally under its own minted id and
            // may be retried once or more before the app sees it succeed.
            'acp_id' => ['sometimes', 'uuid'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        if (! empty($validated['acp_id'])) {
            $existing = ActivityParticipant::where('acp_id', $validated['acp_id'])
                ->where('acp_activity_id', $activity->act_id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'Participant added successfully.',
                    'data' => ['id' => $existing->acp_id, 'name' => $existing->acp_name],
                ], 201);
            }
        }

        try {
            $participant = ActivityParticipant::create([
                'acp_id' => $validated['acp_id'] ?? null,
                'acp_activity_id' => $activity->act_id,
                'acp_name' => $validated['name'],
            ]);
        } catch (QueryException $e) {
            // Same opportunistic-sync race as Activity::store — see there.
            if (empty($validated['acp_id']) || ! $this->isUniqueViolation($e)) {
                throw $e;
            }
            $participant = ActivityParticipant::where('acp_id', $validated['acp_id'])
                ->where('acp_activity_id', $activity->act_id)
                ->first();
            if (! $participant) {
                throw $e;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Participant added successfully.',
            'data' => ['id' => $participant->acp_id, 'name' => $participant->acp_name],
        ], 201);
    }

    public function removeParticipant(Request $request, Activity $activity, ActivityParticipant $participant)
    {
        $this->authorizeOwner($request, $activity);
        $this->assertInProgress($activity);

        if ($participant->acp_activity_id !== $activity->act_id) {
            abort(404);
        }

        $participant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Participant removed successfully.',
            'data' => null,
        ]);
    }

    /**
     * Uploads one geo-tagged, optionally-captioned photo — the ranger's app
     * watermarks it client-side before this ever gets called; the server
     * just compresses and stores it (same as patrol incident/case photos).
     */
    public function addMedia(Request $request, Activity $activity)
    {
        $this->authorizeOwner($request, $activity);
        $this->assertInProgress($activity);

        $validated = $request->validate([
            // Same client-id idempotency as Activity::store — a photo added
            // offline is queued locally under its own minted id and may be
            // retried once or more before the app sees it succeed.
            'acm_id' => ['sometimes', 'uuid'],
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:15360'],
            'caption' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        if (! empty($validated['acm_id'])) {
            $existing = ActivityMedia::where('acm_id', $validated['acm_id'])
                ->where('acm_activity_id', $activity->act_id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'Photo added successfully.',
                    'data' => [
                        'id' => $existing->acm_id,
                        'caption' => $existing->acm_caption,
                        'file_size' => $existing->acm_file_size,
                    ],
                ], 201);
            }
        }

        $stored = $this->photos->compressAndStore($request->file('photo'), 'activity-media/'.$activity->act_id);

        try {
            $media = ActivityMedia::create([
                'acm_id' => $validated['acm_id'] ?? null,
                'acm_activity_id' => $activity->act_id,
                'acm_disk' => 'local',
                'acm_file_path' => $stored['path'],
                'acm_file_size' => $stored['size'],
                'acm_caption' => $validated['caption'] ?? null,
                'acm_latitude' => $validated['latitude'] ?? null,
                'acm_longitude' => $validated['longitude'] ?? null,
            ]);
        } catch (QueryException $e) {
            // Same opportunistic-sync race as Activity::store — see there.
            // The just-compressed/stored file above becomes an orphan in
            // this case (the row that won the race already stored its own
            // copy) — harmless, just wasted disk, not worth cleaning up
            // here at the cost of losing the original QueryException if
            // that cleanup itself failed.
            if (empty($validated['acm_id']) || ! $this->isUniqueViolation($e)) {
                throw $e;
            }
            $media = ActivityMedia::where('acm_id', $validated['acm_id'])
                ->where('acm_activity_id', $activity->act_id)
                ->first();
            if (! $media) {
                throw $e;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Photo added successfully.',
            'data' => [
                'id' => $media->acm_id,
                'caption' => $media->acm_caption,
                'file_size' => $media->acm_file_size,
            ],
        ], 201);
    }

    /**
     * Streams one activity photo — same ownership rule as every other
     * action here (the ranger must have created this activity), reached
     * through the media row's own relation rather than trusting an id in
     * the URL, exactly like the admin panel's equivalent
     * {@see \App\Http\Controllers\Api\V1\AdminActivityController::media}.
     */
    public function media(Request $request, ActivityMedia $media)
    {
        $this->authorizeOwner($request, $media->activity);

        return Storage::disk($media->acm_disk)->response($media->acm_file_path);
    }

    /**
     * Closes the activity out with a report/conclusion. Participants and
     * photos already reached the server as they were added (unlike a
     * patrol, this endpoint has nothing else to bundle up).
     */
    public function end(Request $request, Activity $activity)
    {
        $this->authorizeOwner($request, $activity);

        // Idempotent: a queued `end_activity` sync retried after its first
        // attempt actually succeeded (network dropped before the app saw
        // the response) would otherwise 409 forever with no way to clear
        // the "still syncing" state locally — since ending is terminal,
        // reporting the already-completed activity back as success is
        // exactly as correct as the original request would have been.
        if ($activity->act_status === Activity::STATUS_COMPLETED) {
            $activity->load(['participants', 'media']);

            return response()->json([
                'success' => true,
                'message' => 'Activity ended successfully.',
                'data' => new ActivityResource($activity),
            ]);
        }

        $this->assertInProgress($activity);

        $validated = $request->validate([
            'report' => ['nullable', 'string', 'max:5000'],
        ]);

        $activity->update([
            'act_report' => $validated['report'] ?? null,
            'act_status' => Activity::STATUS_COMPLETED,
            'act_ended_at' => now(),
        ]);

        $activity->load(['participants', 'media']);

        return response()->json([
            'success' => true,
            'message' => 'Activity ended successfully.',
            'data' => new ActivityResource($activity),
        ]);
    }

    private function authorizeOwner(Request $request, Activity $activity): void
    {
        if ($activity->act_created_by !== $request->user()->u_id) {
            abort(403, 'You are not the owner of this activity.');
        }
    }

    private function assertInProgress(Activity $activity): void
    {
        if ($activity->act_status !== Activity::STATUS_IN_PROGRESS) {
            abort(409, 'This activity has already ended.');
        }
    }
}
