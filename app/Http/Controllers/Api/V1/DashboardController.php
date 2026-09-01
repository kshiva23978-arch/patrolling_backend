<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CaseEntry;
use App\Models\PatrolIncident;
use App\Models\PatrollingEntries;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Real-time counts for the field app's dashboard — defaults to "this
     * month" but accepts `from`/`to` to filter to any custom date range
     * instead (the dashboard's date-range filter) — scoped to every patrol
     * entry in the ranger's assigned range(s), not just the ones they
     * personally led — plus the cases and incidents ("activities") reported
     * on those entries. Matches how both the app's own Patrolling/Case
     * history lists and the admin panel's dashboard already scope (range
     * membership, not authorship): a range or field-staff ranger should see
     * their whole range's activity here, same as everywhere else. Pass
     * `range_id` (one of the ranger's assigned ranges) to further scope
     * every count to just that range — the dashboard's range selector, shown
     * when the ranger has more than one.
     *
     * The trend is always the last 7 calendar days regardless of `from`/`to`
     * — it's a "recent activity" sparkline, not a breakdown of the selected
     * totals window.
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'range_id' => ['sometimes', 'uuid', 'exists:ranges,rn_id'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);

        $rangeId = $validated['range_id'] ?? null;

        if ($rangeId !== null && ! $user->ranges()->where('rn_id', $rangeId)->exists()) {
            abort(403, 'You do not have access to this range.');
        }

        $rangeIds = $rangeId !== null ? [$rangeId] : $user->ranges()->pluck('ranges.rn_id');

        $periodStart = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : Carbon::now()->startOfMonth();
        $periodEnd = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : Carbon::now()->endOfMonth();

        $entriesQuery = PatrollingEntries::query()->whereIn('pe_range_id', $rangeIds);

        $patrolEntryIds = (clone $entriesQuery)->pluck('pe_id');

        $patrollingsInPeriod = (clone $entriesQuery)
            ->whereBetween('pe_patrol_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->count();

        $casesInPeriod = CaseEntry::query()
            ->whereIn('ce_range_id', $rangeIds)
            ->whereBetween('ce_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->count();

        $activitiesInPeriod = PatrolIncident::query()
            ->whereIn('pi_entry_id', $patrolEntryIds)
            ->whereBetween('pi_reported_at', [$periodStart, $periodEnd])
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard stats retrieved successfully.',
            'data' => [
                'period' => [
                    'from' => $periodStart->toDateString(),
                    'to' => $periodEnd->toDateString(),
                ],
                'patrollings' => [
                    'total' => $patrollingsInPeriod,
                    'trend' => $this->dailyTrend($entriesQuery, 'pe_patrol_date'),
                ],
                'cases' => [
                    'total' => $casesInPeriod,
                    'trend' => $this->dailyTrend(
                        CaseEntry::query()->whereIn('ce_range_id', $rangeIds),
                        'ce_date'
                    ),
                ],
                'activities' => [
                    'total' => $activitiesInPeriod,
                    'trend' => $this->dailyTrend(
                        PatrolIncident::query()->whereIn('pi_entry_id', $patrolEntryIds),
                        'pi_reported_at'
                    ),
                ],
            ],
        ]);
    }

    /**
     * Counts per day for the last 7 days (oldest first), for the given
     * query and date column.
     *
     * @return array<int, int>
     */
    private function dailyTrend($query, string $dateColumn): array
    {
        $days = collect(range(6, 0))->map(fn ($offset) => Carbon::now()->subDays($offset)->toDateString());

        $counts = (clone $query)
            ->whereBetween($dateColumn, [
                Carbon::now()->subDays(6)->startOfDay(),
                Carbon::now()->endOfDay(),
            ])
            ->get([$dateColumn])
            ->groupBy(fn ($row) => Carbon::parse($row->{$dateColumn})->toDateString())
            ->map->count();

        return $days->map(fn ($day) => $counts->get($day, 0))->values()->all();
    }
}
