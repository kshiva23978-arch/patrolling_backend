<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RangeCustomFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->rcf_id,
            'range_id' => $this->rcf_range_id,
            'field_name' => $this->rcf_field_name,
            'field_key' => $this->rcf_field_key,
            'input_type' => $this->rcf_input_type,
            'options' => $this->rcf_options ?? [],
            'is_required' => $this->rcf_is_required,
            'is_active' => $this->rcf_is_active,
            'sort_order' => $this->rcf_sort_order,
            'created_at' => $this->rcf_created_at?->toISOString(),
            'updated_at' => $this->rcf_updated_at?->toISOString(),
        ];
    }
}
