<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->rn_id,
            'range_id' => $this->rn_range_id,
            'range_name' => $this->rn_range_name,
            'range_headquarter' => $this->rn_range_headquarter,
            'key_activities' => $this->rn_key_activities,
            'patrolling_modes' => PatrollingModeResource::collection($this->whenLoaded('patrollingModes')),
            'created_at' => $this->rn_created_at?->toISOString(),
            'updated_at' => $this->rn_updated_at?->toISOString(),
        ];
    }
}
