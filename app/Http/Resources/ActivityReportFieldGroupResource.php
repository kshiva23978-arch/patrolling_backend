<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityReportFieldGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->arfg_id,
            'category_id' => $this->arfg_category_id,
            'name' => $this->arfg_name,
            'key' => $this->arfg_key,
            'sort_order' => $this->arfg_sort_order,
            'fields' => ActivityReportFieldResource::collection($this->whenLoaded('fields')),
            'created_at' => $this->arfg_created_at?->toISOString(),
            'updated_at' => $this->arfg_updated_at?->toISOString(),
        ];
    }
}
