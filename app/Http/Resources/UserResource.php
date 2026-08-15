<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->u_id,
            'employee_id' => $this->u_employee_id,
            'role' => $this->u_role_id,
            'designation' => $this->u_designation_id,
            'status' => $this->u_status,
            'created_at' => $this->u_created_at?->toISOString(),
            'updated_at' => $this->u_updated_at?->toISOString(),
        ];
    }
}
