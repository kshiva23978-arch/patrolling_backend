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
                'start_odometer' => $v->cev_start_odometer,
                'end_odometer' => $v->cev_end_odometer,
                'distance' => $v->cev_distance,
            ])),
            'staff_names' => $this->ce_staff_names ?? [],
            'incharge_staff' => $this->ce_incharge_staff,
            'staff_deployed_count' => $this->ce_staff_deployed_count,
            'start_location' => [
                'latitude' => $this->ce_start_latitude,
                'longitude' => $this->ce_start_longitude,
                'address' => $this->ce_start_address,
            ],
            'end_location' => [
                'latitude' => $this->ce_end_latitude,
                'longitude' => $this->ce_end_longitude,
                'address' => $this->ce_end_address,
            ],
            'total_distance' => $this->ce_total_distance,
            'incident_occurred' => $this->ce_incident_occurred,
            'case_filed' => $this->ce_case_filed,
            'report' => $this->ce_report,
            'incidents' => $this->whenLoaded('incidents', fn () => $this->incidents->map(fn ($i) => [
                'id' => $i->cei_id,
                'name' => $i->cei_name,
                'details' => $i->cei_details,
                'status' => $i->cei_status,
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
                'photo_count' => $f->relationLoaded('media') ? $f->media->count() : 0,
                'reported_at' => $f->cef_reported_at?->toISOString(),
            ])),
            'notes' => $this->whenLoaded('notes', fn () => $this->notes->map(fn ($n) => [
                'id' => $n->cen_id,
                'text' => $n->cen_text,
                'created_at' => $n->cen_created_at?->toISOString(),
            ])),
            'closing_photo_count' => $this->whenLoaded('closingMedia', fn () => $this->closingMedia->count()),
            'current_travel_mode' => $this->ce_current_travel_mode,
            'current_vehicle_id' => $this->ce_current_vehicle_id,
            'started_at' => $this->ce_started_at?->toISOString(),
            'ended_at' => $this->ce_ended_at?->toISOString(),
            'created_at' => $this->ce_created_at?->toISOString(),
            'updated_at' => $this->ce_updated_at?->toISOString(),
        ];
    }
}
