<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * {@see CaseEntryResource} plus the leader's identity — the app-facing
 * resource omits it (a ranger always knows who they are), but the admin
 * panel's "Cases" listing needs it, same as {@see AdminPatrolEntryResource}
 * adds `patrol_leader` on top of the app-facing patrol entry shape.
 */
class AdminCaseEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge(
            (new CaseEntryResource($this->resource))->toArray($request),
            [
                'leader' => $this->whenLoaded('leader', fn () => [
                    'id' => $this->leader->u_id,
                    'employee_id' => $this->leader->u_employee_id,
                    'name' => $this->leader->details?->ud_fullname,
                ]),
            ]
        );
    }
}
