<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ScopesToRanges;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminPatrolEntryResource;
use App\Http\Resources\PatrolEntryCommentResource;
use App\Http\Resources\PatrolRoutePointResource;
use App\Models\PatrolCaseMedia;
use App\Models\PatrolEntryComment;
use App\Models\PatrolIncidentMedia;
use App\Models\PatrollingEntries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Read-only visibility into every ranger's patrols, for the admin panel's
 * "Patrollings" section — unlike {@see \App\Http\Controllers\Api\V1\PatrolEntryController},
 * which is scoped to the field app's own authenticated ranger.
 */
class AdminPatrolEntryController extends Controller
{
    use ScopesToRanges;

    /**
     * All patrol entries, in-progress first, newest first — optionally
     * filtered by status and/or range. Pass `type=case` or `type=all` to
     * fold case reports into the same listing (see {@see indexUnified}) —
     * the admin panel's "Patrollings" table filters between the two so the
     * `status` filter (pending/in_progress/completed) only ever applies to
     * patrolling rows, never case rows (cases have their own open/closed
     * status instead).
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'completed'])],
            'range_id' => ['sometimes', 'uuid', 'exists:ranges,rn_id'],
            'type' => ['sometimes', Rule::in(['patrolling', 'case', 'all'])],
        ]);

        if (isset($validated['range_id'])) {
            $this->assertRangeAccessible($request, $validated['range_id']);
        }

        $type = $validated['type'] ?? 'patrolling';

        if ($type !== 'patrolling') {
            return $this->indexUnified($request, $validated, $type);
        }

        $entriesQuery = PatrollingEntries::query()
            ->where('pe_type', PatrollingEntries::TYPE_PATROLLING)
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
            );
        $entriesQuery = $this->scopeToAccessibleRanges($entriesQuery, $request, 'pe_range_id');

        $entries = $entriesQuery
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

    /**
     * A flattened, type-tagged listing across patrol entries and case
     * reports (`type` = `patrolling` | `case` on each row) — built as a SQL
     * union so it can be sorted and paginated as one list rather than
     * merging two separately-paginated queries in PHP. Every row carries
     * `entry_id` (the owning patrol entry) so the admin panel can link a
     * case row straight to that patrol's detail page, where its case
     * reports are already shown.
     *
     * @param  array<string, mixed>  $validated
     */
    private function indexUnified(Request $request, array $validated, string $type)
    {
        $rangeId = $validated['range_id'] ?? null;
        $status = $validated['status'] ?? null;
        $accessibleRangeIds = $this->accessibleRangeIds($request);

        $patrolRows = DB::table('pe_patrolling_entries as pe')
            ->leftJoin('ranges as r', 'r.rn_id', '=', 'pe.pe_range_id')
            ->leftJoin('beats as b', 'b.bt_id', '=', 'pe.pe_beat_id')
            ->leftJoin('users as u', 'u.u_id', '=', 'pe.pe_patrol_leader_id')
            ->leftJoin('user_details as ud', 'ud.ud_user_id', '=', 'u.u_id')
            ->where('pe.pe_type', PatrollingEntries::TYPE_PATROLLING)
            ->when($rangeId, fn ($q, $id) => $q->where('pe.pe_range_id', $id))
            ->when($status, fn ($q, $s) => $q->where('pe.pe_status', $s))
            ->when($accessibleRangeIds !== null, fn ($q) => $q->whereIn('pe.pe_range_id', $accessibleRangeIds))
            ->selectRaw("
                pe.pe_id as id,
                'patrolling' as type,
                pe.pe_id as entry_id,
                pe.pe_patrol_id as reference,
                pe.pe_status as status,
                r.rn_range_name as range_name,
                b.bt_name as beat_name,
                u.u_employee_id as leader_employee_id,
                ud.ud_fullname as leader_name,
                pe.pe_patrol_date as record_date,
                pe.pe_created_at as sort_at
            ");

        $caseRows = DB::table('patrol_case_reports as pcr')
            ->join('pe_patrolling_entries as pe', 'pe.pe_id', '=', 'pcr.pcr_entry_id')
            ->leftJoin('ranges as r', 'r.rn_id', '=', 'pe.pe_range_id')
            ->leftJoin('beats as b', 'b.bt_id', '=', 'pe.pe_beat_id')
            ->leftJoin('users as u', 'u.u_id', '=', 'pcr.pcr_reported_by')
            ->leftJoin('user_details as ud', 'ud.ud_user_id', '=', 'u.u_id')
            ->when($rangeId, fn ($q, $id) => $q->where('pe.pe_range_id', $id))
            ->when($accessibleRangeIds !== null, fn ($q) => $q->whereIn('pe.pe_range_id', $accessibleRangeIds))
            ->selectRaw("
                pcr.pcr_id as id,
                'case' as type,
                pe.pe_id as entry_id,
                pcr.pcr_case_number as reference,
                pcr.pcr_status as status,
                r.rn_range_name as range_name,
                b.bt_name as beat_name,
                u.u_employee_id as leader_employee_id,
                ud.ud_fullname as leader_name,
                pcr.pcr_reported_at::date as record_date,
                pcr.pcr_created_at as sort_at
            ");

        $query = $type === 'case' ? $caseRows : $patrolRows->unionAll($caseRows);

        $rows = $query->orderByDesc('sort_at')->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Patrol entries retrieved successfully.',
            'data' => collect($rows->items())->map(fn ($row) => [
                'id' => $row->id,
                'type' => $row->type,
                'entry_id' => $row->entry_id,
                'reference' => $row->reference,
                'status' => $row->status,
                'range_name' => $row->range_name,
                'beat_name' => $row->beat_name,
                'leader_employee_id' => $row->leader_employee_id,
                'leader_name' => $row->leader_name,
                'date' => $row->record_date,
            ]),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, PatrollingEntries $entry)
    {
        $this->assertRangeAccessible($request, $entry->pe_range_id);

        $entry->load([
            'range', 'beat', 'patrolType', 'modes', 'vehicles.vehicle',
            'patrolLeader.details', 'caseReports.media', 'incidents.media', 'customFieldValues.customField',
            'comments.admin',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Patrol entry retrieved successfully.',
            'data' => new AdminPatrolEntryResource($entry),
        ]);
    }

    /**
     * Adds an admin comment to a patrol entry — the admin panel's own
     * running discussion/annotation thread on a patrol, separate from a
     * ranger's in-app notes. Any admin with `manage` access to this section
     * can comment, on a patrol in any status (pending, in progress, or
     * completed), since this is about the record rather than a live action.
     */
    public function storeComment(Request $request, PatrollingEntries $entry)
    {
        $this->assertRangeAccessible($request, $entry->pe_range_id);

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
        ]);

        $comment = PatrolEntryComment::create([
            'pec_entry_id' => $entry->pe_id,
            'pec_admin_id' => $request->user()->a_id,
            'pec_text' => $validated['text'],
            'pec_created_at' => now(),
        ]);
        $comment->setRelation('admin', $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully.',
            'data' => new PatrolEntryCommentResource($comment),
        ], 201);
    }

    /**
     * The entry's GPS trail, oldest first. Pass `since` (an ISO timestamp)
     * to fetch only points recorded after it — what the live-tracking map
     * polls on an interval instead of re-fetching the whole trail.
     */
    public function routePoints(Request $request, PatrollingEntries $entry)
    {
        $this->assertRangeAccessible($request, $entry->pe_range_id);

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
     * Deletes a patrol entry outright — its case reports, incidents, route
     * points, vehicles, notes, and custom field values all cascade at the DB
     * level (see the `pe_patrolling_entries`-referencing FKs'
     * `cascadeOnDelete()`), but their photo files on disk don't, so those are
     * removed explicitly first to avoid leaving them orphaned.
     */
    public function destroy(Request $request, PatrollingEntries $entry)
    {
        $this->assertRangeAccessible($request, $entry->pe_range_id);

        $entry->load(['caseReports.media', 'incidents.media']);

        foreach ($entry->caseReports as $caseReport) {
            foreach ($caseReport->media as $media) {
                Storage::disk($media->pcm_disk)->delete($media->pcm_file_path);
            }
        }
        foreach ($entry->incidents as $incident) {
            foreach ($incident->media as $media) {
                Storage::disk($media->pim_disk)->delete($media->pim_file_path);
            }
        }

        $entry->delete();

        return response()->json([
            'success' => true,
            'message' => 'Patrol entry deleted successfully.',
            'data' => null,
        ]);
    }

    /**
     * Streams the selfie the ranger captured to start this patrol — see
     * {@see \App\Http\Controllers\Api\V1\PatrolEntryController::startPatrol}.
     */
    public function startSelfie(Request $request, PatrollingEntries $entry)
    {
        $this->assertRangeAccessible($request, $entry->pe_range_id);

        if ($entry->pe_start_selfie_path === null) {
            abort(404);
        }

        return Storage::disk($entry->pe_start_selfie_disk)->response($entry->pe_start_selfie_path);
    }

    /**
     * Streams a case-report photo. Photos are stored privately (not on a
     * publicly-served disk), so the admin panel proxies this through its
     * own server-side route rather than linking to it directly.
     */
    public function caseMedia(Request $request, PatrolCaseMedia $media)
    {
        $this->assertRangeAccessible($request, $media->caseReport->entry->pe_range_id);

        return Storage::disk($media->pcm_disk)->response($media->pcm_file_path);
    }

    /**
     * Streams an incident photo — see {@see caseMedia}.
     */
    public function incidentMedia(Request $request, PatrolIncidentMedia $media)
    {
        $this->assertRangeAccessible($request, $media->incident->entry->pe_range_id);

        return Storage::disk($media->pim_disk)->response($media->pim_file_path);
    }
}
