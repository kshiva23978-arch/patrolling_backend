<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatrolTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->pt_id,
            'name' => $this->pt_name,
            'description' => $this->pt_description,
            'status' => $this->pt_status,
            'categories' => $this->categories(),
            'created_at' => $this->pt_created_at?->toISOString(),
            'updated_at' => $this->pt_updated_at?->toISOString(),
        ];
    }
}
