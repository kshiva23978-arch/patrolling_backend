<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatrolIncidentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->pi_id,
            'entry_id' => $this->pi_entry_id,
            'name' => $this->pi_name,
            'details' => $this->pi_details,
            'location' => [
                'latitude' => $this->pi_latitude,
                'longitude' => $this->pi_longitude,
                'address' => $this->pi_address,
            ],
            'reported_at' => $this->pi_reported_at?->toISOString(),
            'photos' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'id' => $m->pim_id,
                'file_size' => $m->pim_file_size,
                'latitude' => $m->pim_latitude,
                'longitude' => $m->pim_longitude,
            ])),
        ];
    }
}
