<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->st_id,
            'name' => $this->st_name,
            'designation_id' => $this->st_designation_id,
            'range_id' => $this->st_range_id,
            'status' => $this->st_status,
            'created_at' => $this->st_created_at?->toISOString(),
            'updated_at' => $this->st_updated_at?->toISOString(),
        ];
    }
}
