<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CountryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->co_id,
            'country_name' => $this->co_country_name,
            'created_at' => $this->co_created_at?->toISOString(),
            'updated_at' => $this->co_updated_at?->toISOString(),
        ];
    }
}
