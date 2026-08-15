<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatrollingModeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->pm_id,
            'mode_name' => $this->pm_mode_name,
            'created_at' => $this->pm_created_at?->toISOString(),
            'updated_at' => $this->pm_updated_at?->toISOString(),
        ];
    }
}
