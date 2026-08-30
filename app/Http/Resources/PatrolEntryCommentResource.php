<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatrolEntryCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->pec_id,
            'text' => $this->pec_text,
            // Admin accounts carry no separate display name (see
            // `Admin::getAuthIdentifierName`) — the employee id is what the
            // admin panel shows as "who" everywhere else too (e.g. Topbar).
            'added_by' => $this->admin?->a_employee_id,
            'created_at' => $this->pec_created_at?->toISOString(),
        ];
    }
}
