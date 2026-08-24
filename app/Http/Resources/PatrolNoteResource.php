<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatrolNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->pn_id,
            'text' => $this->pn_text,
            'created_at' => $this->pn_created_at?->toISOString(),
        ];
    }
}
