<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DestinationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ds_id,
            'name' => $this->ds_name,
            'status' => $this->ds_status,
            'created_at' => $this->ds_created_at?->toISOString(),
            'updated_at' => $this->ds_updated_at?->toISOString(),
        ];
    }
}
