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
     * Real-time "this month" counts for the field app's dashboard, scoped to
     * every patrol entry in the ranger's assigned range(s) — not just the
     * ones they personally led — plus the cases and incidents ("activities")
     * reported on those entries. Matches how both the app's own Patrolling/
     * Case history lists and the admin panel's dashboard already scope
     * (range membership, not authorship): a range or field-staff ranger
     * should see their whole range's activity here, same as everywhere else.
     * Each also carries a day-by-day count for the last 7 days for the
     * sparkline trend. Pass `range_id` (one of the ranger's assigned
     * ranges) to further scope every count to just that range — the
     * dashboard's range selector, shown when the ranger has more than one.
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'range_id' => ['sometimes', 'uuid', 'exists:ranges,rn_id'],
        ]);

        $rangeId = $validated['range_id'] ?? null;

        if ($rangeId !== null && ! $user->ranges()->where('rn_id', $rangeId)->exists()) {
            abort(403, 'You do not have access to this range.');
        }

        $rangeIds = $rangeId !== null ? [$rangeId] : $user->ranges()->pluck('ranges.rn_id');

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $entriesQuery = PatrollingEntries::query()->whereIn('pe_range_id', $rangeIds);

        $patrolEntryIds = (clone $entriesQuery)->pluck('pe_id');

        $patrollingsThisMonth = (clone $entriesQuery)
            ->whereBetween('pe_patrol_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $casesThisMonth = CaseEntry::query()
            ->whereIn('ce_range_id', $rangeIds)
            ->whereBetween('ce_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $activitiesThisMonth = PatrolIncident::query()
            ->whereIn('pi_entry_id', $patrolEntryIds)
            ->whereBetween('pi_reported_at', [$monthStart, $monthEnd])
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard stats retrieved successfully.',
            'data' => [
                'patrollings' => [
                    'total' => $patrollingsThisMonth,
                    'trend' => $this->dailyTrend($entriesQuery, 'pe_patrol_date'),
                ],
                'cases' => [
                    'total' => $casesThisMonth,
                    'trend' => $this->dailyTrend(
                        CaseEntry::query()->whereIn('ce_range_id', $rangeIds),
                        'ce_date'
                    ),
                ],
                'activities' => [
                    'total' => $activitiesThisMonth,
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
