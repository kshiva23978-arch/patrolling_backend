<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityReportFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->arf_id,
            'category_id' => $this->arf_category_id,
            'group_id' => $this->arf_group_id,
            'field_name' => $this->arf_field_name,
            'field_key' => $this->arf_field_key,
            'input_type' => $this->arf_input_type,
            'options' => $this->arf_options ?? [],
            'is_required' => $this->arf_is_required,
            'is_active' => $this->arf_is_active,
            'sort_order' => $this->arf_sort_order,
            'created_at' => $this->arf_created_at?->toISOString(),
            'updated_at' => $this->arf_updated_at?->toISOString(),
        ];
    }
}
