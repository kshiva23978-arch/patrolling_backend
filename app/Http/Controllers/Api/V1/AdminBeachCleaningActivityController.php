<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ScopesToDestinations;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminBeachCleaningActivityResource;
use App\Models\Beach;
use App\Models\BeachCleaningActivity;
use App\Models\BeachCleaningMedia;
use App\Models\Countries;
use App\Models\WasteCategory;
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
    ];

    // What the admin list table (BeachCleaningActivitiesTable.tsx) actually
    // renders — activity_name/status/bags/weight/created_at are plain
    // columns needing no relation. Deliberately narrower than {@see WITH}:
    // media/segregations (and the report `buildReport()` computes from
    // segregations) cost real joins + payload for every one of the 15 rows
    // on a page, purely for the detail page's ({@see show}) benefit — the
    // Resource's `whenLoaded` calls simply omit those keys when unloaded,
    // so this is a safe, response-shape-preserving trim.
    private const INDEX_WITH = ['destination', 'beach', 'createdBy.details'];

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in([BeachCleaningActivity::STATUS_IN_PROGRESS, BeachCleaningActivity::STATUS_SUBMITTED])],
            'destination_id' => ['sometimes', 'uuid'],
            'beach_id' => ['sometimes', 'uuid'],
            'created_by' => ['sometimes', 'uuid'],
        ]);

        $activities = BeachCleaningActivity::query()
            ->with(self::INDEX_WITH)
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('bca_status', $status))
            ->when($validated['destination_id'] ?? null, fn ($q, $id) => $q->where('bca_destination_id', $id))
            ->when($validated['beach_id'] ?? null, fn ($q, $id) => $q->where('bca_beach_id', $id))
            ->when($validated['created_by'] ?? null, fn ($q, $id) => $q->where('bca_created_by', $id))
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

    /**
     * Beach-wise collection totals — how much each beach has yielded across
     * its drives, scoped by the same filters as {@see index}. Grouped in the
     * database rather than in PHP since the drive count can grow large.
     */
    public function stats(Request $request)
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in([BeachCleaningActivity::STATUS_IN_PROGRESS, BeachCleaningActivity::STATUS_SUBMITTED])],
            'destination_id' => ['sometimes', 'uuid'],
            'beach_id' => ['sometimes', 'uuid'],
            'created_by' => ['sometimes', 'uuid'],
        ]);

        $rows = BeachCleaningActivity::query()
            ->selectRaw('
                bca_beach_id,
                COUNT(*) as activities_count,
                COALESCE(SUM(bca_participant_count), 0) as participant_count,
                COALESCE(SUM(bca_bags_collected), 0) as bags_collected,
                COALESCE(SUM(bca_total_weight_kg), 0) as total_weight_kg,
                AVG(bca_segregation_percent) as avg_segregation_percent
            ')
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('bca_status', $status))
            ->when($validated['destination_id'] ?? null, fn ($q, $id) => $q->where('bca_destination_id', $id))
            ->when($validated['beach_id'] ?? null, fn ($q, $id) => $q->where('bca_beach_id', $id))
            ->when($validated['created_by'] ?? null, fn ($q, $id) => $q->where('bca_created_by', $id))
            ->tap(fn ($q) => $this->scopeToAccessibleRanges($q, $request, 'bca_destination_id'))
            ->groupBy('bca_beach_id')
            ->get();

        $beaches = Beach::query()
            ->whereIn('bc_id', $rows->pluck('bca_beach_id')->filter()->values())
            ->with('destination')
            ->get()
            ->keyBy('bc_id');

        $data = $rows
            ->map(function ($row) use ($beaches) {
                $beach = $row->bca_beach_id ? $beaches->get($row->bca_beach_id) : null;

                return [
                    'beach' => $beach ? ['id' => $beach->bc_id, 'name' => $beach->bc_name] : null,
                    'destination' => $beach?->destination
                        ? ['id' => $beach->destination->ds_id, 'name' => $beach->destination->ds_name]
                        : null,
                    'activities_count' => (int) $row->activities_count,
                    'participant_count' => (int) $row->participant_count,
                    'bags_collected' => (int) $row->bags_collected,
                    'total_weight_kg' => round((float) $row->total_weight_kg, 2),
                    'avg_segregation_percent' => $row->avg_segregation_percent !== null
                        ? round((float) $row->avg_segregation_percent, 2)
                        : null,
                ];
            })
            ->sortByDesc('total_weight_kg')
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Beach cleaning stats retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Total collected weight (KG), broken down by waste category and by
     * destination, across every drive matching [$request]'s filters/range
     * scope — same filters as {@see index}, but unpaginated: this powers a
     * dashboard-style summary above the (paginated) list, not one page's
     * worth of drives. Both breakdowns sum straight from each country-wise
     * segregation row's own `bcs_weight_kg` (see
     * `BeachCleaningActivityResource::buildReport`'s per-activity version of
     * the same sum) — `by_category` groups those rows by waste category
     * regardless of which drive/destination they came from, `by_destination`
     * groups them by the *drive's* destination (a segregation row has no
     * destination of its own; every row in one drive shares its one
     * activity-level destination). Placed as its own route ahead of
     * `{beachCleaningActivity}` in routes/api.php, same reasoning as
     * {@see rangers}.
     */
    public function weightSummary(Request $request)
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in([BeachCleaningActivity::STATUS_IN_PROGRESS, BeachCleaningActivity::STATUS_SUBMITTED])],
            'destination_id' => ['sometimes', 'uuid'],
            'beach_id' => ['sometimes', 'uuid'],
            'created_by' => ['sometimes', 'uuid'],
        ]);

        $activities = BeachCleaningActivity::query()
            ->with(['destination', 'segregations.wasteCategory', 'segregations.country'])
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('bca_status', $status))
            ->when($validated['destination_id'] ?? null, fn ($q, $id) => $q->where('bca_destination_id', $id))
            ->when($validated['beach_id'] ?? null, fn ($q, $id) => $q->where('bca_beach_id', $id))
            ->when($validated['created_by'] ?? null, fn ($q, $id) => $q->where('bca_created_by', $id))
            ->tap(fn ($q) => $this->scopeToAccessibleRanges($q, $request, 'bca_destination_id'))
            ->get();

        $byCategory = [];
        $byDestination = [];
        $byCountry = [];

        foreach ($activities as $activity) {
            $destinationName = $activity->destination?->ds_name ?? 'Unspecified';

            foreach ($activity->segregations as $segregation) {
                if ($segregation->bcs_weight_kg === null) {
                    continue;
                }

                $categoryName = $segregation->wasteCategory?->wc_name ?? 'Uncategorized';
                $countryName = $segregation->country?->co_country_name ?? 'Unspecified';
                $weight = (float) $segregation->bcs_weight_kg;

                $byCategory[$categoryName] = ($byCategory[$categoryName] ?? 0) + $weight;
                $byDestination[$destinationName] = ($byDestination[$destinationName] ?? 0) + $weight;
                $byCountry[$countryName] = ($byCountry[$countryName] ?? 0) + $weight;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Weight summary retrieved successfully.',
            'data' => [
                'by_category' => collect($byCategory)->map(fn ($kg, $name) => ['name' => $name, 'weight_kg' => round($kg, 2)])->values(),
                'by_destination' => collect($byDestination)->map(fn ($kg, $name) => ['name' => $name, 'weight_kg' => round($kg, 2)])->values(),
                'by_country' => collect($byCountry)->map(fn ($kg, $name) => ['name' => $name, 'weight_kg' => round($kg, 2)])->values(),
                'total_weight_kg' => round(array_sum($byCategory), 2),
            ],
        ]);
    }

    /**
     * The fixed "Waste Segregation Format for Sample of 10% (Lot)" report —
     * every country (row) × waste category (column) cell is the sum of
     * `bcs_quantity_kg` ("No.s") across every drive matching [$request]'s
     * filters/range scope, same shape as the paper form this replaces:
     * every one of the 22 countries and 9 categories always appears, in the
     * same fixed order they were seeded in (`co_created_at`/`wc_created_at`
     * — matches the form exactly), with `0` standing in for "nothing
     * recorded" rather than the row/column being omitted. `total_bags` is
     * `bca_bags_collected` summed across matching drives — that field is
     * only ever recorded once per drive, not per country, so it has no
     * per-row breakdown the way the paper form's own "No. of Bags" column
     * implies; callers should show it as a single grand total, not a column.
     * Also returns a `remaining_*` version of every figure — the paper
     * form's own title says this is only ever a 10% *sample*, so those
     * estimate the other ~90% that was never individually sorted/counted,
     * by scaling each recorded cell up by (that drive's own sample-rate
     * multiplier minus one) — deliberately the *remaining* share on its
     * own, not the full 100% (`matrix` + `remaining_matrix`), since a
     * combined 100% figure isn't wanted here.
     */
    public function report(Request $request)
    {
        $validated = $request->validate([
            'destination_id' => ['sometimes', 'uuid'],
            'beach_id' => ['sometimes', 'uuid'],
            'created_by' => ['sometimes', 'uuid'],
            'country_id' => ['sometimes', 'uuid'],
            // Scopes the whole report down to one specific drive — the
            // per-activity "Download Report" link on the detail page uses
            // this instead of the other filters, which are for the
            // multi-drive report page.
            'activity_id' => ['sometimes', 'uuid'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
        ]);

        $activities = BeachCleaningActivity::query()
            ->with(['segregations.country', 'segregations.wasteCategory'])
            ->when($validated['activity_id'] ?? null, fn ($q, $id) => $q->where('bca_id', $id))
            ->when($validated['destination_id'] ?? null, fn ($q, $id) => $q->where('bca_destination_id', $id))
            ->when($validated['beach_id'] ?? null, fn ($q, $id) => $q->where('bca_beach_id', $id))
            ->when($validated['created_by'] ?? null, fn ($q, $id) => $q->where('bca_created_by', $id))
            ->when($validated['date_from'] ?? null, fn ($q, $d) => $q->whereDate('bca_created_at', '>=', $d))
            ->when($validated['date_to'] ?? null, fn ($q, $d) => $q->whereDate('bca_created_at', '<=', $d))
            ->tap(fn ($q) => $this->scopeToAccessibleRanges($q, $request, 'bca_destination_id'))
            ->get();

        // A `country_id` filter narrows the grid down to that one
        // waste-origin country's row rather than filtering `$activities`
        // itself — a drive can record segregation rows for several
        // countries at once, so the meaningful filter is "only show this
        // country's row", not "only show drives that mention it".
        $countriesQuery = Countries::orderBy('co_created_at');
        if (! empty($validated['country_id'])) {
            $countriesQuery->where('co_id', $validated['country_id']);
        }
        $countries = $countriesQuery->pluck('co_country_name')->all();
        $categories = WasteCategory::orderBy('wc_created_at')->pluck('wc_name')->all();

        $matrix = [];
        $remainingMatrix = [];
        // Parallel to $matrix/$remainingMatrix but summing `bcs_weight_kg`
        // (the actual kilogram weight) instead of `bcs_quantity_kg` (the
        // "Nos." count field — see the loop below's doc comment on that
        // misleading name).
        $weightMatrix = [];
        $remainingWeightMatrix = [];
        foreach ($countries as $country) {
            $matrix[$country] = array_fill_keys($categories, 0.0);
            $remainingMatrix[$country] = array_fill_keys($categories, 0.0);
            $weightMatrix[$country] = array_fill_keys($categories, 0.0);
            $remainingWeightMatrix[$country] = array_fill_keys($categories, 0.0);
        }

        $totalBags = 0;
        foreach ($activities as $activity) {
            $totalBags += (int) ($activity->bca_bags_collected ?? 0);

            // This drive's own recorded sample rate (the paper form's "SAMPLE
            // OF 10% (LOT)") — its segregation rows only ever cover that
            // fraction of what was actually collected, so estimating the
            // other ~90% means scaling *this activity's* rows by its own
            // multiplier before summing, not applying one flat 9x to an
            // already-mixed total (a drive with an unusual percent would
            // otherwise throw the whole estimate off). Falls back to the
            // standard 10% when unset, same default the app itself locks
            // step 4's "Segregation" field to. `- 1` because this is the
            // *remaining*, not-yet-sorted share on its own — the full 100%
            // multiplier minus the 1x already recorded in `matrix`.
            $percent = (float) ($activity->bca_segregation_percent ?? 10.0);
            $remainingMultiplier = ($percent > 0 ? 100 / $percent : 10.0) - 1;

            foreach ($activity->segregations as $segregation) {
                $countryName = $segregation->country?->co_country_name;
                $categoryName = $segregation->wasteCategory?->wc_name;

                // A country/category since removed from master data (see
                // the segregations migration's nullOnDelete doc comment) has
                // nowhere left in this fixed grid to land — its quantity is
                // real history, but this report only ever shows the current
                // master list's rows/columns, so it's excluded here rather
                // than silently widening the table.
                if ($countryName === null || $categoryName === null) {
                    continue;
                }
                if (! isset($matrix[$countryName][$categoryName])) {
                    continue;
                }

                $quantity = (float) $segregation->bcs_quantity_kg;
                $matrix[$countryName][$categoryName] += $quantity;
                $remainingMatrix[$countryName][$categoryName] += $quantity * $remainingMultiplier;

                $weight = (float) ($segregation->bcs_weight_kg ?? 0.0);
                $weightMatrix[$countryName][$categoryName] += $weight;
                $remainingWeightMatrix[$countryName][$categoryName] += $weight * $remainingMultiplier;
            }
        }

        $countryTotals = [];
        $categoryTotals = array_fill_keys($categories, 0.0);
        $grandTotal = 0.0;
        $remainingCountryTotals = [];
        $remainingCategoryTotals = array_fill_keys($categories, 0.0);
        $remainingGrandTotal = 0.0;

        $weightCountryTotals = [];
        $weightCategoryTotals = array_fill_keys($categories, 0.0);
        $weightGrandTotal = 0.0;
        $remainingWeightCountryTotals = [];
        $remainingWeightCategoryTotals = array_fill_keys($categories, 0.0);
        $remainingWeightGrandTotal = 0.0;

        foreach ($matrix as $country => $row) {
            $rowTotal = array_sum($row);
            $countryTotals[$country] = round($rowTotal, 2);
            $grandTotal += $rowTotal;
            foreach ($row as $category => $value) {
                $categoryTotals[$category] += $value;
            }

            $remainingRowTotal = array_sum($remainingMatrix[$country]);
            $remainingCountryTotals[$country] = round($remainingRowTotal, 2);
            $remainingGrandTotal += $remainingRowTotal;
            foreach ($remainingMatrix[$country] as $category => $value) {
                $remainingCategoryTotals[$category] += $value;
            }

            $weightRowTotal = array_sum($weightMatrix[$country]);
            $weightCountryTotals[$country] = round($weightRowTotal, 2);
            $weightGrandTotal += $weightRowTotal;
            foreach ($weightMatrix[$country] as $category => $value) {
                $weightCategoryTotals[$category] += $value;
            }

            $remainingWeightRowTotal = array_sum($remainingWeightMatrix[$country]);
            $remainingWeightCountryTotals[$country] = round($remainingWeightRowTotal, 2);
            $remainingWeightGrandTotal += $remainingWeightRowTotal;
            foreach ($remainingWeightMatrix[$country] as $category => $value) {
                $remainingWeightCategoryTotals[$category] += $value;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Report generated successfully.',
            'data' => [
                'countries' => $countries,
                'categories' => $categories,
                'matrix' => collect($matrix)->map(fn ($row) => collect($row)->map(fn ($v) => round($v, 2)))->all(),
                'country_totals' => $countryTotals,
                'category_totals' => collect($categoryTotals)->map(fn ($v) => round($v, 2))->all(),
                'grand_total' => round($grandTotal, 2),
                'total_bags' => $totalBags,
                'activity_count' => $activities->count(),
                // The estimated *remaining* ~90% never individually
                // sorted/counted — each recorded (sampled) cell above,
                // scaled by (its own drive's sample-rate multiplier minus
                // one) and re-summed. Bags aren't part of this: `total_bags`
                // is an actual physical count of every bag collected, not a
                // sample, so it isn't scaled.
                'remaining_matrix' => collect($remainingMatrix)->map(fn ($row) => collect($row)->map(fn ($v) => round($v, 2)))->all(),
                'remaining_country_totals' => $remainingCountryTotals,
                'remaining_category_totals' => collect($remainingCategoryTotals)->map(fn ($v) => round($v, 2))->all(),
                'remaining_grand_total' => round($remainingGrandTotal, 2),

                // Same shapes as above, in kilograms (`bcs_weight_kg`)
                // instead of item counts ("Nos.").
                'weight_matrix' => collect($weightMatrix)->map(fn ($row) => collect($row)->map(fn ($v) => round($v, 2)))->all(),
                'weight_country_totals' => $weightCountryTotals,
                'weight_category_totals' => collect($weightCategoryTotals)->map(fn ($v) => round($v, 2))->all(),
                'weight_grand_total' => round($weightGrandTotal, 2),
                'remaining_weight_matrix' => collect($remainingWeightMatrix)->map(fn ($row) => collect($row)->map(fn ($v) => round($v, 2)))->all(),
                'remaining_weight_country_totals' => $remainingWeightCountryTotals,
                'remaining_weight_category_totals' => collect($remainingWeightCategoryTotals)->map(fn ($v) => round($v, 2))->all(),
                'remaining_weight_grand_total' => round($remainingWeightGrandTotal, 2),
            ],
        ]);
    }

    /**
     * Distinct rangers who have logged at least one beach cleaning drive
     * visible to [$request]'s admin — populates the admin panel's "Ranger"
     * filter dropdown without pulling in the full (range-scoped, name-less)
     * `/admin/users` listing, which doesn't fit this destination-scoped
     * screen. Placed as its own route ahead of `{beachCleaningActivity}` in
     * routes/api.php so "rangers" isn't swallowed by that route's implicit
     * model binding.
     */
    public function rangers(Request $request)
    {
        $rangers = BeachCleaningActivity::query()
            ->tap(fn ($q) => $this->scopeToAccessibleRanges($q, $request, 'bca_destination_id'))
            ->with('createdBy.details')
            ->get()
            ->pluck('createdBy')
            ->filter()
            ->unique('u_id')
            ->map(fn ($u) => [
                'id' => $u->u_id,
                'employee_id' => $u->u_employee_id,
                'name' => $u->details?->ud_fullname,
            ])
            ->sortBy(fn ($u) => $u['name'] ?? $u['employee_id'])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Rangers retrieved successfully.',
            'data' => $rangers,
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
