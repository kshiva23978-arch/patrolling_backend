<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Beats;
use App\Models\PatrolCaseReports;
use App\Models\PatrolIncident;
use App\Models\PatrollingEntries;
use App\Models\Ranges;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Top-of-page summary tiles for the admin panel's Dashboard — optionally
 * scoped to a single range and/or a patrol-date range.
 */
class AdminDashboardController extends Controller
{
    public function stats(Request $request)
    {
        $validated = $request->validate([
            'range_id' => ['sometimes', 'uuid', 'exists:ranges,rn_id'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $rangeId = $validated['range_id'] ?? null;
        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->toDateString() : null;
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->toDateString() : null;

        $entryIdsInRange = $rangeId
            ? PatrollingEntries::query()->where('pe_range_id', $rangeId)->pluck('pe_id')
            : null;

        $rangesCount = Ranges::query()
            ->when($rangeId, fn ($q, $id) => $q->where('rn_id', $id))
            ->count();

        $beatsCount = Beats::query()
            ->when($rangeId, fn ($q, $id) => $q->where('bt_range_id', $id))
            ->count();

        $patrollingsCount = PatrollingEntries::query()
            ->when($rangeId, fn ($q, $id) => $q->where('pe_range_id', $id))
            ->when($from, fn ($q, $date) => $q->where('pe_patrol_date', '>=', $date))
            ->when($to, fn ($q, $date) => $q->where('pe_patrol_date', '<=', $date))
            ->count();

        $livePatrollingsCount = PatrollingEntries::query()
            ->when($rangeId, fn ($q, $id) => $q->where('pe_range_id', $id))
            ->where('pe_status', PatrollingEntries::STATUS_IN_PROGRESS)
            ->count();

        $casesCount = PatrolCaseReports::query()
            ->when($entryIdsInRange, fn ($q, $ids) => $q->whereIn('pcr_entry_id', $ids))
            ->when($from, fn ($q, $date) => $q->whereDate('pcr_reported_at', '>=', $date))
            ->when($to, fn ($q, $date) => $q->whereDate('pcr_reported_at', '<=', $date))
            ->count();

        $incidentsCount = PatrolIncident::query()
            ->when($entryIdsInRange, fn ($q, $ids) => $q->whereIn('pi_entry_id', $ids))
            ->when($from, fn ($q, $date) => $q->whereDate('pi_reported_at', '>=', $date))
            ->when($to, fn ($q, $date) => $q->whereDate('pi_reported_at', '<=', $date))
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard stats retrieved successfully.',
            'data' => [
                'ranges' => $rangesCount,
                'beats' => $beatsCount,
                'patrollings' => $patrollingsCount,
                'live_patrollings' => $livePatrollingsCount,
                'cases' => $casesCount,
                'incidents' => $incidentsCount,
            ],
        ]);
    }
}
