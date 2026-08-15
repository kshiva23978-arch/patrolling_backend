<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ud_id,
            'user_id' => $this->ud_user_id,
            'fullname' => $this->ud_fullname,
            'mobile_number' => $this->ud_mobile_number,
            'email' => $this->ud_email,
        ];
    }
}
