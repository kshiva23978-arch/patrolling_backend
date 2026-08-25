<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->bt_id,
            'range_id' => $this->bt_range_id,
            'name' => $this->bt_name,
            'status' => $this->bt_status,
            'boundary' => $this->boundaryGeoJson(),
            'created_at' => $this->bt_created_at?->toISOString(),
            'updated_at' => $this->bt_updated_at?->toISOString(),
        ];
    }
}
