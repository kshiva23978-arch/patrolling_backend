<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminBeachCleaningActivityResource extends JsonResource
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
            'officer' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->u_id,
                'employee_id' => $this->createdBy->u_employee_id,
                'name' => $this->createdBy->details?->ud_fullname,
            ] : null),
            'summary' => $this->bca_summary,
            'participant_count' => $this->bca_participant_count,
            'bags_collected' => $this->bca_bags_collected,
            'total_weight_kg' => $this->bca_total_weight_kg,
            'segregation_percent' => $this->bca_segregation_percent,
            'closing_report' => $this->bca_closing_report,
            'media' => $this->whenLoaded(
                'media',
                fn () => $this->media->map(fn ($m) => [
                    'id' => $m->bcm_id,
                    'kind' => $m->bcm_kind,
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
                ]),
            ),
            'report' => $this->whenLoaded('segregations', fn () => $this->buildReport()),
            'submitted_at' => $this->bca_submitted_at?->toISOString(),
            'created_at' => $this->bca_created_at?->toISOString(),
        ];
    }

    private function buildReport(): array
    {
        $byCategory = [];
        $byCountry = [];

        foreach ($this->segregations as $segregation) {
            $categoryName = $segregation->wasteCategory?->wc_name ?? 'Uncategorized';
            $countryName = $segregation->country?->co_country_name ?? 'Unspecified';
            $qty = (float) $segregation->bcs_quantity_kg;

            $byCategory[$categoryName] = ($byCategory[$categoryName] ?? 0) + $qty;
            $byCountry[$countryName] = ($byCountry[$countryName] ?? 0) + $qty;
        }

        return [
            'by_category' => collect($byCategory)->map(fn ($qty, $name) => ['name' => $name, 'quantity_kg' => round($qty, 2)])->values(),
            'by_country' => collect($byCountry)->map(fn ($qty, $name) => ['name' => $name, 'quantity_kg' => round($qty, 2)])->values(),
            'segregated_total_kg' => round(array_sum($byCategory), 2),
        ];
    }
}
