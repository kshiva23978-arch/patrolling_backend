<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatrolCaseReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->pcr_id,
            'entry_id' => $this->pcr_entry_id,
            'case_number' => $this->pcr_case_number,
            'details' => $this->pcr_details,
            'location' => [
                'latitude' => $this->pcr_latitude,
                'longitude' => $this->pcr_longitude,
                'address' => $this->pcr_address,
            ],
            'reported_at' => $this->pcr_reported_at?->toISOString(),
            'photos' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'id' => $m->pcm_id,
                'file_size' => $m->pcm_file_size,
                'latitude' => $m->pcm_latitude,
                'longitude' => $m->pcm_longitude,
            ])),
        ];
    }
}
