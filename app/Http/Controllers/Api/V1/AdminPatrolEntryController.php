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
            'comments.admin', 'comments.user.details',
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
     * Distinct values the admin report page offers in its multi-select
     * filters — every staff name ever entered on a patrol the calling admin
     * can see, each with the range(s) it was deployed in, so the page can
     * narrow the list to the ranges currently selected and label each name
     * with its range. Names are unique case-insensitively (free-typed, so
     * "Suresh" and "suresh" are one person); a name deployed in several
     * ranges lists all of them.
     */
    public function reportOptions(Request $request)
    {
        $rows = PatrollingEntries::query()
            ->where('pe_type', PatrollingEntries::TYPE_PATROLLING)
            ->tap(fn ($q) => $this->scopeToAccessibleRanges($q, $request, 'pe_range_id'))
            ->whereNotNull('pe_staff_names')
            ->with('range')
            ->get(['pe_id', 'pe_range_id', 'pe_staff_names']);

        $staff = [];
        foreach ($rows as $row) {
            foreach ((array) $row->pe_staff_names as $raw) {
                $name = trim((string) $raw);
                if ($name === '') {
                    continue;
                }
                $key = mb_strtolower($name);
                $staff[$key] ??= ['name' => $name, 'ranges' => []];
                if ($row->pe_range_id !== null) {
                    $staff[$key]['ranges'][$row->pe_range_id] = [
                        'id' => $row->pe_range_id,
                        'name' => $row->range?->rn_range_name ?? 'Unknown range',
                    ];
                }
            }
        }
        uksort($staff, fn ($a, $b) => strnatcasecmp($a, $b));

        return response()->json([
            'success' => true,
            'message' => 'Report options retrieved successfully.',
            'data' => [
                'staff' => array_values(array_map(
                    fn ($s) => ['name' => $s['name'], 'ranges' => array_values($s['ranges'])],
                    $staff,
                )),
            ],
        ]);
    }

    /**
     * The admin "Patrol Report": every patrol matching the filters, each
     * with its full GPS trail (so the page can plot all of them on one
     * map), plus roll-up totals. Every filter is a list — nothing selected
     * means "all" — and `case_recorded` / `incident_recorded` take
     * `yes`/`no` values so "only patrols that logged a case" and "only
     * those that didn't" are both expressible.
     *
     * Not paginated: a report is generated for a deliberately narrowed
     * slice (a range and a date window), and the map needs every trail at
     * once. Capped at [$maxEntries] as a safety net; the response says when
     * that cap was hit so the page can ask for a narrower filter.
     */
    public function report(Request $request)
    {
        $validated = $request->validate([
            'range_ids' => ['sometimes', 'array'],
            'range_ids.*' => ['uuid'],
            'staff_names' => ['sometimes', 'array'],
            'staff_names.*' => ['string', 'max:150'],
            'case_recorded' => ['sometimes', 'array'],
            'case_recorded.*' => [Rule::in(['yes', 'no'])],
            'incident_recorded' => ['sometimes', 'array'],
            'incident_recorded.*' => [Rule::in(['yes', 'no'])],
            'status' => ['sometimes', 'array'],
            'status.*' => [Rule::in(['pending', 'in_progress', 'completed'])],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ]);

        $rangeIds = array_values(array_unique($validated['range_ids'] ?? []));
        foreach ($rangeIds as $rangeId) {
            $this->assertRangeAccessible($request, $rangeId);
        }

        // A yes/no multi-select with both (or neither) chosen is no filter.
        $yesNo = function (array $values): ?bool {
            $values = array_values(array_unique($values));

            return count($values) === 1 ? $values[0] === 'yes' : null;
        };
        $caseRecorded = $yesNo($validated['case_recorded'] ?? []);
        $incidentRecorded = $yesNo($validated['incident_recorded'] ?? []);
        $staffNames = array_values(array_filter(array_map('trim', $validated['staff_names'] ?? [])));

        $maxEntries = 300;

        $query = PatrollingEntries::query()
            ->where('pe_type', PatrollingEntries::TYPE_PATROLLING)
            ->with([
                'range', 'beat', 'patrolType', 'modes', 'vehicles.vehicle',
                'patrolLeader.details', 'caseReports.media', 'incidents.media',
                'routePoints.vehicle',
            ])
            ->tap(fn ($q) => $this->scopeToAccessibleRanges($q, $request, 'pe_range_id'))
            ->when($rangeIds !== [], fn ($q) => $q->whereIn('pe_range_id', $rangeIds))
            ->when(! empty($validated['status']), fn ($q) => $q->whereIn('pe_status', $validated['status']))
            ->when($validated['date_from'] ?? null, fn ($q, $d) => $q->whereDate('pe_patrol_date', '>=', $d))
            ->when($validated['date_to'] ?? null, fn ($q, $d) => $q->whereDate('pe_patrol_date', '<=', $d))
            ->when($caseRecorded === true, fn ($q) => $q->whereHas('caseReports'))
            ->when($caseRecorded === false, fn ($q) => $q->whereDoesntHave('caseReports'))
            ->when($incidentRecorded === true, fn ($q) => $q->whereHas('incidents'))
            ->when($incidentRecorded === false, fn ($q) => $q->whereDoesntHave('incidents'))
            // "Any of the selected staff was deployed" — `pe_staff_names` is
            // a JSON array of free-typed names, matched case-insensitively.
            ->when($staffNames !== [], function ($q) use ($staffNames) {
                $q->where(function ($inner) use ($staffNames) {
                    foreach ($staffNames as $name) {
                        $inner->orWhereRaw(
                            'EXISTS (SELECT 1 FROM jsonb_array_elements_text(pe_staff_names::jsonb) AS n WHERE lower(n) = ?)',
                            [mb_strtolower($name)],
                        );
                    }
                });
            });

        $total = (clone $query)->count();
        $entries = $query
            ->orderBy('pe_patrol_date')
            ->orderBy('pe_start_time')
            ->limit($maxEntries)
            ->get();

        $totalDistanceKm = 0.0;
        $caseCount = 0;
        $incidentCount = 0;
        $byRange = [];
        $distances = [];
        foreach ($entries as $entry) {
            $distance = (float) ($entry->distanceSummary()['total_km'] ?? 0);
            $distances[$entry->pe_id] = $distance;
            $totalDistanceKm += $distance;
            $caseCount += $entry->caseReports->count();
            $incidentCount += $entry->incidents->count();

            $rangeName = $entry->range?->rn_range_name ?? 'Unassigned';
            $byRange[$rangeName] ??= ['range' => $rangeName, 'patrols' => 0, 'distance_km' => 0.0, 'cases' => 0, 'incidents' => 0];
            $byRange[$rangeName]['patrols']++;
            $byRange[$rangeName]['distance_km'] += $distance;
            $byRange[$rangeName]['cases'] += $entry->caseReports->count();
            $byRange[$rangeName]['incidents'] += $entry->incidents->count();
        }
        ksort($byRange, SORT_NATURAL | SORT_FLAG_CASE);

        $data = AdminPatrolEntryResource::collection($entries)->resolve($request);
        foreach ($entries as $i => $entry) {
            $data[$i]['route_points'] = PatrolRoutePointResource::collection($entry->routePoints)->resolve($request);
            $data[$i]['distance_km'] = round($distances[$entry->pe_id], 3);
        }

        return response()->json([
            'success' => true,
            'message' => 'Patrol report generated successfully.',
            'data' => [
                'entries' => $data,
                'summary' => [
                    'patrol_count' => $entries->count(),
                    'matched_count' => $total,
                    'truncated' => $total > $entries->count(),
                    'total_distance_km' => round($totalDistanceKm, 3),
                    'case_count' => $caseCount,
                    'incident_count' => $incidentCount,
                    'by_range' => array_values(array_map(
                        fn ($row) => [...$row, 'distance_km' => round($row['distance_km'], 3)],
                        $byRange,
                    )),
                ],
            ],
        ]);
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
            ->orderBy('prp_id')
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
     * Streams the selfie the ranger captured to end this patrol — see
     * {@see \App\Http\Controllers\Api\V1\PatrolEntryController::endPatrol}.
     */
    public function endSelfie(Request $request, PatrollingEntries $entry)
    {
        $this->assertRangeAccessible($request, $entry->pe_range_id);

        if ($entry->pe_end_selfie_path === null) {
            abort(404);
        }

        return Storage::disk($entry->pe_end_selfie_disk)->response($entry->pe_end_selfie_path);
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
