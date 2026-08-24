<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PatrolCaseReports;
use App\Models\PatrolIncident;
use App\Models\PatrollingEntries;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Real-time "this month" counts for the field app's dashboard, scoped
     * to the ranger's own patrol entries — patrollings they've logged, plus
     * the cases and incidents ("activities") reported on those entries.
     * Each also carries a day-by-day count for the last 7 days for the
     * sparkline trend. Pass `range_id` (one of the ranger's assigned
     * ranges) to further scope every count to just that range — the
     * dashboard's range selector, shown when the ranger has more than one.
     */
    public function stats(Request $request)
    {
        $user = $request->user();
        $userId = $user->u_id;

        $validated = $request->validate([
            'range_id' => ['sometimes', 'uuid', 'exists:ranges,rn_id'],
        ]);

        $rangeId = $validated['range_id'] ?? null;

        if ($rangeId !== null && ! $user->ranges()->where('rn_id', $rangeId)->exists()) {
            abort(403, 'You do not have access to this range.');
        }

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $entriesQuery = PatrollingEntries::query()
            ->where('pe_patrol_leader_id', $userId)
            ->when($rangeId, fn ($query, $id) => $query->where('pe_range_id', $id));

        $patrolEntryIds = (clone $entriesQuery)->pluck('pe_id');

        $patrollingsThisMonth = (clone $entriesQuery)
            ->whereBetween('pe_patrol_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $casesThisMonth = PatrolCaseReports::query()
            ->whereIn('pcr_entry_id', $patrolEntryIds)
            ->whereBetween('pcr_reported_at', [$monthStart, $monthEnd])
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
                        PatrolCaseReports::query()->whereIn('pcr_entry_id', $patrolEntryIds),
                        'pcr_reported_at'
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
