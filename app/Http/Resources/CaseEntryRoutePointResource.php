<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseEntryRoutePointResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->cerp_id,
            'latitude' => $this->cerp_latitude,
            'longitude' => $this->cerp_longitude,
            'travel_mode' => $this->cerp_travel_mode,
            'vehicle_type' => $this->vehicle?->cev_vehicle_type,
            'recorded_at' => $this->cerp_recorded_at?->toISOString(),
        ];
    }
}
