<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->act_id,
            'name' => $this->act_name,
            'description' => $this->act_description,
            'conducted_by' => $this->act_conducted_by,
            'status' => $this->act_status,
            'location' => [
                'latitude' => $this->act_latitude,
                'longitude' => $this->act_longitude,
                'address' => $this->act_address,
            ],
            'report' => $this->act_report,
            'started_at' => $this->act_started_at?->toISOString(),
            'ended_at' => $this->act_ended_at?->toISOString(),
            // See PatrolEntryResource::createdViaCurrentToken — same
            // reasoning, now that an in-progress activity also blocks the
            // cross-module "one active item at a time" rule (see
            // UnfinishedWorkChecker): the app needs to tell "my own
            // in-progress activity, on this device" apart from "this
            // ranger's other device has one going".
            'is_this_device' => $this->createdViaCurrentToken($request),
            'participants' => $this->whenLoaded(
                'participants',
                fn () => $this->participants->map(fn ($p) => [
                    'id' => $p->acp_id,
                    'name' => $p->acp_name,
                ]),
            ),
            'media' => $this->whenLoaded(
                'media',
                fn () => $this->media->map(fn ($m) => [
                    'id' => $m->acm_id,
                    'url' => route('app.activity-media', $m->acm_id),
                    'caption' => $m->acm_caption,
                    'file_size' => $m->acm_file_size,
                    'latitude' => $m->acm_latitude,
                    'longitude' => $m->acm_longitude,
                    'created_at' => $m->acm_created_at?->toISOString(),
                ]),
            ),
        ];
    }

    /** See PatrolEntryResource::createdViaCurrentToken — same reasoning. */
    private function createdViaCurrentToken(Request $request): bool
    {
        $currentTokenId = $request->user()?->currentAccessToken()?->id;

        return $this->act_created_via_token_id !== null
            && $currentTokenId !== null
            && (int) $this->act_created_via_token_id === (int) $currentTokenId;
    }
}
