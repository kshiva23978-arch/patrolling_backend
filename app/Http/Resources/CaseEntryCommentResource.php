<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** See {@see PatrolEntryCommentResource} — identical shape, for the Case module. */
class CaseEntryCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authorType = $this->cec_admin_id !== null ? 'admin' : 'ranger';

        $authorName = $authorType === 'admin'
            ? $this->admin?->a_employee_id
            : ($this->user?->details?->ud_fullname ?: $this->user?->u_employee_id);

        $requester = $request->user();
        $isMine = $authorType === 'ranger'
            && $requester instanceof User
            && $this->cec_user_id === $requester->u_id;

        return [
            'id' => $this->cec_id,
            'text' => $this->cec_text,
            'added_by' => $this->admin?->a_employee_id,
            'author_name' => $authorName,
            'author_type' => $authorType,
            'is_mine' => $isMine,
            'created_at' => $this->cec_created_at?->toISOString(),
            'updated_at' => $this->cec_updated_at?->toISOString(),
        ];
    }
}
