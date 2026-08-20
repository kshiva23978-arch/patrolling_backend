<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatrolRoutePointResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->prp_id,
            'latitude' => $this->prp_latitude,
            'longitude' => $this->prp_longitude,
            'travel_mode' => $this->prp_travel_mode,
            // Not `whenLoaded` — the controller always eager-loads `vehicle`,
            // and a missing key (rather than null) here would break the
            // frontend's icon selection for every point on the map.
            'vehicle_type' => $this->vehicle?->pev_vehicle_type,
            'recorded_at' => $this->prp_recorded_at?->toISOString(),
        ];
    }
}
