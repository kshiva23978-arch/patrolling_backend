<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeachResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->bc_id,
            'destination_id' => $this->bc_destination_id,
            'name' => $this->bc_name,
            'status' => $this->bc_status,
            'created_at' => $this->bc_created_at?->toISOString(),
            'updated_at' => $this->bc_updated_at?->toISOString(),
        ];
    }
}
