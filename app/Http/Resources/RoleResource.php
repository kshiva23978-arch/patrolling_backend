<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ro_id,
            'name' => $this->ro_name,
            'description' => $this->ro_description,
            'status' => $this->ro_status,
            'permissions' => $this->ro_permissions,
            'level' => $this->ro_level,
        ];
    }
}
