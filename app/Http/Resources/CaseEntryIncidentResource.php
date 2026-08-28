<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseEntryIncidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->cei_id,
            'case_id' => $this->cei_case_id,
            'name' => $this->cei_name,
            'details' => $this->cei_details,
            'status' => $this->cei_status,
            'location' => [
                'latitude' => $this->cei_latitude,
                'longitude' => $this->cei_longitude,
                'address' => $this->cei_address,
            ],
            'reported_at' => $this->cei_reported_at?->toISOString(),
            'photos' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'id' => $m->ceim_id,
                'file_size' => $m->ceim_file_size,
                'latitude' => $m->ceim_latitude,
                'longitude' => $m->ceim_longitude,
            ])),
        ];
    }
}
