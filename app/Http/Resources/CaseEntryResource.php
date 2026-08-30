<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ce_id,
            'case_number' => $this->ce_case_number,
            'status' => $this->ce_status,
            'date' => $this->ce_date?->toDateString(),
            'start_time' => $this->ce_start_time,
            'end_time' => $this->ce_end_time,
            'range' => $this->whenLoaded('range', fn () => [
                'id' => $this->range->rn_id,
                'name' => $this->range->rn_range_name,
            ]),
            'beat' => $this->whenLoaded('beat', fn () => $this->beat ? [
                'id' => $this->beat->bt_id,
                'name' => $this->beat->bt_name,
            ] : null),
            'area_covered' => $this->ce_area_covered,
            'case_type' => $this->ce_case_type,
            'modes' => $this->whenLoaded('modes', fn () => $this->modes->map(fn ($mode) => [
                'id' => $mode->pm_id,
                'name' => $mode->pm_mode_name,
            ])),
            'vehicles' => $this->whenLoaded('vehicles', fn () => $this->vehicles->map(fn ($v) => [
                'id' => $v->cev_id,
                'type' => $v->cev_vehicle_type,
                'registration_no' => $v->vehicle?->vh_registration_number,
                'is_current' => $this->ce_current_vehicle_id !== null && $v->cev_id === $this->ce_current_vehicle_id,
                'start_odometer' => $this->toFloat($v->cev_start_odometer),
                'end_odometer' => $this->toFloat($v->cev_end_odometer),
                'distance' => $this->toFloat($v->cev_distance),
            ])),
            'staff_names' => $this->ce_staff_names ?? [],
            'incharge_staff' => $this->ce_incharge_staff,
            'staff_deployed_count' => $this->ce_staff_deployed_count,
            'start_location' => [
                'latitude' => $this->toFloat($this->ce_start_latitude),
                'longitude' => $this->toFloat($this->ce_start_longitude),
                'address' => $this->ce_start_address,
            ],
            'end_location' => [
                'latitude' => $this->toFloat($this->ce_end_latitude),
                'longitude' => $this->toFloat($this->ce_end_longitude),
                'address' => $this->ce_end_address,
            ],
            'start_selfie_url' => $this->ce_start_selfie_path
                ? route('app.case-start-selfie', $this->ce_id)
                : null,
            'total_distance' => $this->toFloat($this->ce_total_distance),
            'incident_occurred' => $this->ce_incident_occurred,
            'case_filed' => $this->ce_case_filed,
            'report' => $this->ce_report,
            'incidents' => $this->whenLoaded('incidents', fn () => $this->incidents->map(fn ($i) => [
                'id' => $i->cei_id,
                'name' => $i->cei_name,
                'details' => $i->cei_details,
                'status' => $i->cei_status,
                'location' => [
                    'latitude' => $this->toFloat($i->cei_latitude),
                    'longitude' => $this->toFloat($i->cei_longitude),
                    'address' => $i->cei_address,
                ],
                'photos' => $i->relationLoaded('media') ? $i->media->map(fn ($m) => [
                    'id' => $m->ceim_id,
                    'url' => route('app.case-incident-media', $m->ceim_id),
                ]) : [],
                'photo_count' => $i->relationLoaded('media') ? $i->media->count() : 0,
                'reported_at' => $i->cei_reported_at?->toISOString(),
            ])),
            'filings' => $this->whenLoaded('filings', fn () => $this->filings->map(fn ($f) => [
                'id' => $f->cef_id,
                'filing_number' => $f->cef_filing_number,
                'details' => $f->cef_details,
                'status' => $f->cef_status,
                'conflict_type' => $f->cef_conflict_type,
                'rescue_conducted' => $f->cef_rescue_conducted,
                'species_rescued' => $f->cef_species_rescued,
                'rehab_details' => $f->cef_rehab_details,
                'response_time' => $f->cef_response_time,
                'location' => [
                    'latitude' => $this->toFloat($f->cef_latitude),
                    'longitude' => $this->toFloat($f->cef_longitude),
                    'address' => $f->cef_address,
                ],
                'photos' => $f->relationLoaded('media') ? $f->media->map(fn ($m) => [
                    'id' => $m->cefm_id,
                    'url' => route('app.case-filing-media', $m->cefm_id),
                ]) : [],
                'photo_count' => $f->relationLoaded('media') ? $f->media->count() : 0,
                'reported_at' => $f->cef_reported_at?->toISOString(),
            ])),
            'notes' => $this->whenLoaded('notes', fn () => $this->notes->map(fn ($n) => [
                'id' => $n->cen_id,
                'text' => $n->cen_text,
                'created_at' => $n->cen_created_at?->toISOString(),
            ])),
            'closing_photos' => $this->whenLoaded('closingMedia', fn () => $this->closingMedia->map(fn ($m) => [
                'id' => $m->cecm_id,
                'url' => route('app.case-closing-media', $m->cecm_id),
            ])),
            'closing_photo_count' => $this->whenLoaded('closingMedia', fn () => $this->closingMedia->count()),
            'current_travel_mode' => $this->ce_current_travel_mode,
            'current_vehicle_id' => $this->ce_current_vehicle_id,
            'started_at' => $this->ce_started_at?->toISOString(),
            'ended_at' => $this->ce_ended_at?->toISOString(),
            'created_at' => $this->ce_created_at?->toISOString(),
            'updated_at' => $this->ce_updated_at?->toISOString(),
            // See PatrolEntryResource's matching field for why this exists —
            // same "unfinished patrol/case/activity" rule, scoped per device
            // (see App\Services\UnfinishedWorkChecker).
            'is_this_device' => $this->createdViaCurrentToken($request),
        ];
    }

    /**
     * Postgres `decimal` columns come back from Eloquent as strings (no
     * cast is defined on the underlying models) — every lat/lng here goes
     * through this so the admin panel can do arithmetic/`.toFixed()` on it
     * directly instead of every caller re-coercing.
     */
    private function toFloat(mixed $value): ?float
    {
        return $value !== null ? (float) $value : null;
    }

    /** See PatrolEntryResource::createdViaCurrentToken — same reasoning. */
    private function createdViaCurrentToken(Request $request): bool
    {
        $currentTokenId = $request->user()?->currentAccessToken()?->id;

        return $this->ce_created_via_token_id !== null
            && $currentTokenId !== null
            && (int) $this->ce_created_via_token_id === (int) $currentTokenId;
    }
}
