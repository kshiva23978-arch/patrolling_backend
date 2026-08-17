<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->a_id,
            'employee_id' => $this->a_employee_id,
            'role' => $this->a_role_id,
            'designation' => $this->a_designation_id,
            'status' => $this->a_status,
            'created_at' => $this->a_created_at?->toISOString(),
            'updated_at' => $this->a_updated_at?->toISOString(),
        ];
    }
}
