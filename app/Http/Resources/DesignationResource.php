<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->d_id,
            'designation_name' => $this->d_designation_name,
            'rank_order' => $this->d_rank_order,
            'description' => $this->d_description,
            'status' => $this->d_status,
        
        ];
    }
}
