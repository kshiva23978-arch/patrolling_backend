<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeachCleaningActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->bca_id,
            'activity_name' => $this->bca_activity_name,
            'officer_name' => $this->bca_officer_name,
            'status' => $this->bca_status,
            'destination' => $this->whenLoaded('destination', fn () => $this->destination ? [
                'id' => $this->destination->ds_id,
                'name' => $this->destination->ds_name,
            ] : null),
            'beach' => $this->whenLoaded('beach', fn () => $this->beach ? [
                'id' => $this->beach->bc_id,
                'name' => $this->beach->bc_name,
            ] : null),
            'location' => [
                'latitude' => $this->bca_latitude,
                'longitude' => $this->bca_longitude,
            ],
            'summary' => $this->bca_summary,
            'participant_count' => $this->bca_participant_count,
            'bags_collected' => $this->bca_bags_collected,
            'total_weight_kg' => $this->bca_total_weight_kg,
            'segregation_percent' => $this->bca_segregation_percent,
            'closing_report' => $this->bca_closing_report,
            'handover_to' => $this->bca_handover_to,
            // See PatrolEntryResource::createdViaCurrentToken — same
            // reasoning: the app needs to tell "my own in-progress drive, on
            // this device" apart from "this ranger has one going on another
            // device" (see UnfinishedWorkChecker).
            'is_this_device' => $this->createdViaCurrentToken($request),
            'media' => $this->whenLoaded(
                'media',
                fn () => $this->media->map(fn ($m) => [
                    'id' => $m->bcm_id,
                    'kind' => $m->bcm_kind,
                    'url' => route('app.beach-cleaning-media', $m->bcm_id),
                    'file_size' => $m->bcm_file_size,
                    'latitude' => $m->bcm_latitude,
                    'longitude' => $m->bcm_longitude,
                    'created_at' => $m->bcm_created_at?->toISOString(),
                ]),
            ),
            'segregations' => $this->whenLoaded(
                'segregations',
                fn () => $this->segregations->map(fn ($s) => [
                    'id' => $s->bcs_id,
                    'country' => $s->country ? ['id' => $s->country->co_id, 'name' => $s->country->co_country_name] : null,
                    'waste_category' => $s->wasteCategory ? ['id' => $s->wasteCategory->wc_id, 'name' => $s->wasteCategory->wc_name] : null,
                    'quantity_kg' => $s->bcs_quantity_kg,
                    'weight_kg' => $s->bcs_weight_kg,
                ]),
            ),
            // Step 5's "auto calculated" summary — derived from the
            // segregation rows above rather than stored anywhere, so it's
            // always in sync with whatever rows currently exist.
            'report' => $this->whenLoaded('segregations', fn () => $this->buildReport()),
            'submitted_at' => $this->bca_submitted_at?->toISOString(),
            'created_at' => $this->bca_created_at?->toISOString(),
        ];
    }

    private function buildReport(): array
    {
        $byCategory = [];
        $byCountry = [];
        // Each row's own `bcs_weight_kg` — independent of $byCategory above
        // (that's `bcs_quantity_kg`, a plain count), never derived from it.
        $byCategoryWeight = [];

        foreach ($this->segregations as $segregation) {
            $categoryName = $segregation->wasteCategory?->wc_name ?? 'Uncategorized';
            $countryName = $segregation->country?->co_country_name ?? 'Unspecified';
            $qty = (float) $segregation->bcs_quantity_kg;

            $byCategory[$categoryName] = ($byCategory[$categoryName] ?? 0) + $qty;
            $byCountry[$countryName] = ($byCountry[$countryName] ?? 0) + $qty;

            if ($segregation->bcs_weight_kg !== null) {
                $byCategoryWeight[$categoryName] = ($byCategoryWeight[$categoryName] ?? 0) + (float) $segregation->bcs_weight_kg;
            }
        }

        return [
            'by_category' => collect($byCategory)->map(fn ($qty, $name) => ['name' => $name, 'quantity_kg' => round($qty, 2)])->values(),
            'by_country' => collect($byCountry)->map(fn ($qty, $name) => ['name' => $name, 'quantity_kg' => round($qty, 2)])->values(),
            'by_category_weight' => collect($byCategoryWeight)->map(fn ($kg, $name) => ['name' => $name, 'quantity_kg' => round($kg, 2)])->values(),
            'segregated_total_kg' => round(array_sum($byCategory), 2),
            'segregated_weight_total_kg' => round(array_sum($byCategoryWeight), 2),
        ];
    }

    /** See ActivityResource::createdViaCurrentToken — same reasoning. */
    private function createdViaCurrentToken(Request $request): bool
    {
        $currentTokenId = $request->user()?->currentAccessToken()?->id;

        return $this->bca_created_via_token_id !== null
            && $currentTokenId !== null
            && (int) $this->bca_created_via_token_id === (int) $currentTokenId;
    }
}
