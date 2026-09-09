<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WasteCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->wc_id,
            'name' => $this->wc_name,
            'status' => $this->wc_status,
            'created_at' => $this->wc_created_at?->toISOString(),
            'updated_at' => $this->wc_updated_at?->toISOString(),
        ];
    }
}
