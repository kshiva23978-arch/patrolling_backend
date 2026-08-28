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
            // Postgres `decimal` columns come back from Eloquent as strings
            // (no cast defined on `CaseEntryRoutePoint`) — cast to float so
            // the admin panel's map/distance math gets real numbers.
            'latitude' => (float) $this->cerp_latitude,
            'longitude' => (float) $this->cerp_longitude,
            'travel_mode' => $this->cerp_travel_mode,
            'vehicle_type' => $this->vehicle?->cev_vehicle_type,
            'recorded_at' => $this->cerp_recorded_at?->toISOString(),
        ];
    }
}
