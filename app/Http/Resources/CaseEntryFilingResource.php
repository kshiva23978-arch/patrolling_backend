<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseEntryFilingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->cef_id,
            'case_id' => $this->cef_case_id,
            'filing_number' => $this->cef_filing_number,
            'details' => $this->cef_details,
            'status' => $this->cef_status,
            'conflict_type' => $this->cef_conflict_type,
            'rescue_conducted' => $this->cef_rescue_conducted,
            'species_rescued' => $this->cef_species_rescued,
            'rehab_details' => $this->cef_rehab_details,
            'response_time' => $this->cef_response_time,
            'location' => [
                'latitude' => $this->cef_latitude,
                'longitude' => $this->cef_longitude,
                'address' => $this->cef_address,
            ],
            'reported_at' => $this->cef_reported_at?->toISOString(),
            'photos' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'id' => $m->cefm_id,
                'file_size' => $m->cefm_file_size,
                'latitude' => $m->cefm_latitude,
                'longitude' => $m->cefm_longitude,
            ])),
        ];
    }
}
