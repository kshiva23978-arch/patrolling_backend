<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Jobs\ReverseGeocodeLocation;
use App\Models\Activity;
use App\Models\ActivityMedia;
use App\Models\ActivityParticipant;
use App\Services\PatrolPhotoService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'conducted_by' => ['required', 'string', 'max:150'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $activity = Activity::create([
            'act_name' => $validated['name'],
            'act_description' => $validated['description'] ?? null,
            'act_conducted_by' => $validated['conducted_by'],
            'act_created_by' => $request->user()->u_id,
            'act_status' => Activity::STATUS_IN_PROGRESS,
            'act_latitude' => $validated['latitude'] ?? null,
            'act_longitude' => $validated['longitude'] ?? null,
            'act_started_at' => now(),
        ]);

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

        return response()->json([
            'success' => true,
            'message' => 'Activity created successfully.',
            'data' => new ActivityResource($activity),
        ], 201);
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
            'name' => ['required', 'string', 'max:150'],
        ]);

        $participant = ActivityParticipant::create([
            'acp_activity_id' => $activity->act_id,
            'acp_name' => $validated['name'],
        ]);

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
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:15360'],
            'caption' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $stored = $this->photos->compressAndStore($request->file('photo'), 'activity-media/'.$activity->act_id);

        $media = ActivityMedia::create([
            'acm_activity_id' => $activity->act_id,
            'acm_disk' => 'local',
            'acm_file_path' => $stored['path'],
            'acm_file_size' => $stored['size'],
            'acm_caption' => $validated['caption'] ?? null,
            'acm_latitude' => $validated['latitude'] ?? null,
            'acm_longitude' => $validated['longitude'] ?? null,
        ]);

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
     * Closes the activity out with a report/conclusion. Participants and
     * photos already reached the server as they were added (unlike a
     * patrol, this endpoint has nothing else to bundle up).
     */
    public function end(Request $request, Activity $activity)
    {
        $this->authorizeOwner($request, $activity);
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
