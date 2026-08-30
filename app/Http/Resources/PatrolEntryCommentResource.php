<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatrolEntryCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authorType = $this->pec_admin_id !== null ? 'admin' : 'ranger';

        // Admin accounts carry no separate display name (see
        // `Admin::getAuthIdentifierName`) — the employee id is what the
        // admin panel shows as "who" everywhere else too (e.g. Topbar). A
        // ranger's name comes from `UserDetails`, falling back to their
        // employee id if that's missing.
        $authorName = $authorType === 'admin'
            ? $this->admin?->a_employee_id
            : ($this->user?->details?->ud_fullname ?: $this->user?->u_employee_id);

        $requester = $request->user();
        $isMine = $authorType === 'ranger'
            && $requester instanceof User
            && $this->pec_user_id === $requester->u_id;

        return [
            'id' => $this->pec_id,
            'text' => $this->pec_text,
            // Kept for the admin panel, which has always shown just this.
            'added_by' => $this->admin?->a_employee_id,
            'author_name' => $authorName,
            'author_type' => $authorType,
            'is_mine' => $isMine,
            'created_at' => $this->pec_created_at?->toISOString(),
            'updated_at' => $this->pec_updated_at?->toISOString(),
        ];
    }
}
