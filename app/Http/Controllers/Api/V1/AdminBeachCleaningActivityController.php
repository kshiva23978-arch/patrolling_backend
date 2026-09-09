<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ScopesToDestinations;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminBeachCleaningActivityResource;
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

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in([BeachCleaningActivity::STATUS_IN_PROGRESS, BeachCleaningActivity::STATUS_SUBMITTED])],
            'destination_id' => ['sometimes', 'uuid'],
            'beach_id' => ['sometimes', 'uuid'],
            'created_by' => ['sometimes', 'uuid'],
        ]);

        $activities = BeachCleaningActivity::query()
            ->with(self::WITH)
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
            ->with(['destination', 'segregations.wasteCategory'])
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('bca_status', $status))
            ->when($validated['destination_id'] ?? null, fn ($q, $id) => $q->where('bca_destination_id', $id))
            ->when($validated['beach_id'] ?? null, fn ($q, $id) => $q->where('bca_beach_id', $id))
            ->when($validated['created_by'] ?? null, fn ($q, $id) => $q->where('bca_created_by', $id))
            ->tap(fn ($q) => $this->scopeToAccessibleRanges($q, $request, 'bca_destination_id'))
            ->get();

        $byCategory = [];
        $byDestination = [];

        foreach ($activities as $activity) {
            $destinationName = $activity->destination?->ds_name ?? 'Unspecified';

            foreach ($activity->segregations as $segregation) {
                if ($segregation->bcs_weight_kg === null) {
                    continue;
                }

                $categoryName = $segregation->wasteCategory?->wc_name ?? 'Uncategorized';
                $weight = (float) $segregation->bcs_weight_kg;

                $byCategory[$categoryName] = ($byCategory[$categoryName] ?? 0) + $weight;
                $byDestination[$destinationName] = ($byDestination[$destinationName] ?? 0) + $weight;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Weight summary retrieved successfully.',
            'data' => [
                'by_category' => collect($byCategory)->map(fn ($kg, $name) => ['name' => $name, 'weight_kg' => round($kg, 2)])->values(),
                'by_destination' => collect($byDestination)->map(fn ($kg, $name) => ['name' => $name, 'weight_kg' => round($kg, 2)])->values(),
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
     * Also returns an `estimated_*` version of every figure — the paper
     * form's own title says this is only ever a 10% *sample*, so those
     * scale each recorded cell up by that drive's own sample rate to
     * estimate the full 100% collection (see the loop below).
     */
    public function report(Request $request)
    {
        $validated = $request->validate([
            'destination_id' => ['sometimes', 'uuid'],
            'beach_id' => ['sometimes', 'uuid'],
            'created_by' => ['sometimes', 'uuid'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
        ]);

        $activities = BeachCleaningActivity::query()
            ->with(['segregations.country', 'segregations.wasteCategory'])
            ->when($validated['destination_id'] ?? null, fn ($q, $id) => $q->where('bca_destination_id', $id))
            ->when($validated['beach_id'] ?? null, fn ($q, $id) => $q->where('bca_beach_id', $id))
            ->when($validated['created_by'] ?? null, fn ($q, $id) => $q->where('bca_created_by', $id))
            ->when($validated['date_from'] ?? null, fn ($q, $d) => $q->whereDate('bca_created_at', '>=', $d))
            ->when($validated['date_to'] ?? null, fn ($q, $d) => $q->whereDate('bca_created_at', '<=', $d))
            ->tap(fn ($q) => $this->scopeToAccessibleRanges($q, $request, 'bca_destination_id'))
            ->get();

        $countries = Countries::orderBy('co_created_at')->pluck('co_country_name')->all();
        $categories = WasteCategory::orderBy('wc_created_at')->pluck('wc_name')->all();

        $matrix = [];
        $estimatedMatrix = [];
        foreach ($countries as $country) {
            $matrix[$country] = array_fill_keys($categories, 0.0);
            $estimatedMatrix[$country] = array_fill_keys($categories, 0.0);
        }

        $totalBags = 0;
        foreach ($activities as $activity) {
            $totalBags += (int) ($activity->bca_bags_collected ?? 0);

            // This drive's own recorded sample rate (the paper form's "SAMPLE
            // OF 10% (LOT)") — its segregation rows only ever cover that
            // fraction of what was actually collected, so estimating the
            // other 90%+ means scaling *this activity's* rows by its own
            // multiplier before summing, not applying one flat 10x to an
            // already-mixed total (a drive with an unusual percent would
            // otherwise throw the whole estimate off). Falls back to the
            // standard 10% when unset, same default the app itself locks
            // step 4's "Segregation" field to.
            $percent = (float) ($activity->bca_segregation_percent ?? 10.0);
            $multiplier = $percent > 0 ? 100 / $percent : 10.0;

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
                $estimatedMatrix[$countryName][$categoryName] += $quantity * $multiplier;
            }
        }

        $countryTotals = [];
        $categoryTotals = array_fill_keys($categories, 0.0);
        $grandTotal = 0.0;
        $estimatedCountryTotals = [];
        $estimatedCategoryTotals = array_fill_keys($categories, 0.0);
        $estimatedGrandTotal = 0.0;

        foreach ($matrix as $country => $row) {
            $rowTotal = array_sum($row);
            $countryTotals[$country] = round($rowTotal, 2);
            $grandTotal += $rowTotal;
            foreach ($row as $category => $value) {
                $categoryTotals[$category] += $value;
            }

            $estimatedRowTotal = array_sum($estimatedMatrix[$country]);
            $estimatedCountryTotals[$country] = round($estimatedRowTotal, 2);
            $estimatedGrandTotal += $estimatedRowTotal;
            foreach ($estimatedMatrix[$country] as $category => $value) {
                $estimatedCategoryTotals[$category] += $value;
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
                // The estimated 100% collection — each recorded (sampled)
                // cell above, scaled by its own drive's sample rate and
                // re-summed. `estimated_grand_total` minus `grand_total` is
                // the estimated *remaining* ~90% that was never individually
                // sorted/counted. Bags aren't part of this: `total_bags` is
                // an actual physical count of every bag collected, not a
                // sample, so it isn't scaled.
                'estimated_matrix' => collect($estimatedMatrix)->map(fn ($row) => collect($row)->map(fn ($v) => round($v, 2)))->all(),
                'estimated_country_totals' => $estimatedCountryTotals,
                'estimated_category_totals' => collect($estimatedCategoryTotals)->map(fn ($v) => round($v, 2))->all(),
                'estimated_grand_total' => round($estimatedGrandTotal, 2),
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
