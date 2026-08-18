<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->vh_id,
            'range_id' => $this->vh_range_id,
            'registration_number' => $this->vh_registration_number,
            'type' => $this->vh_type,
            'status' => $this->vh_status,
            'created_at' => $this->vh_created_at?->toISOString(),
            'updated_at' => $this->vh_updated_at?->toISOString(),
        ];
    }
}
