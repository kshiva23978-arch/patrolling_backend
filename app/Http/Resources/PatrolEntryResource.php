<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatrolEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->pe_id,
            'patrol_id' => $this->pe_patrol_id,
            'type' => $this->pe_type,
            'status' => $this->pe_status,
            'date' => $this->pe_patrol_date?->toDateString(),
            'start_time' => $this->pe_start_time,
            'end_time' => $this->pe_end_time,
            'range' => $this->whenLoaded('range', fn () => [
                'id' => $this->range->rn_id,
                'name' => $this->range->rn_range_name,
            ]),
            'beat' => $this->whenLoaded('beat', fn () => $this->beat ? [
                'id' => $this->beat->bt_id,
                'name' => $this->beat->bt_name,
            ] : null),
            'area_covered' => $this->pe_area_covered,
            'area_patrolled' => $this->pe_area_patrolled,
            'patrol_type' => $this->whenLoaded('patrolType', fn () => [
                'id' => $this->patrolType->pt_id,
                'name' => $this->patrolType->pt_name,
            ]),
            'modes' => $this->whenLoaded('modes', fn () => $this->modes->map(fn ($mode) => [
                'id' => $mode->pm_id,
                'name' => $mode->pm_mode_name,
            ])),
            'vehicles' => $this->whenLoaded('vehicles', fn () => $this->vehicles->map(fn ($v) => [
                'id' => $v->pev_id,
                'type' => $v->pev_vehicle_type,
                'registration_no' => $v->vehicle?->vh_registration_number,
                'is_current' => $this->pe_current_vehicle_id !== null && $v->pev_id === $this->pe_current_vehicle_id,
                'start_odometer' => $v->pev_start_odometer,
                'end_odometer' => $v->pev_end_odometer,
                'distance' => $v->pev_distance,
            ])),
            'staff_names' => $this->pe_staff_names ?? [],
            'incharge_staff' => $this->pe_incharge_staff,
            'staff_deployed_count' => $this->pe_staff_deployed_count,
            'gps_enabled' => $this->pe_gps_enabled,
            'start_location' => [
                'latitude' => $this->pe_start_latitude,
                'longitude' => $this->pe_start_longitude,
                'address' => $this->pe_start_address,
            ],
            'end_location' => [
                'latitude' => $this->pe_end_latitude,
                'longitude' => $this->pe_end_longitude,
                'address' => $this->pe_end_address,
            ],
            'start_selfie_url' => $this->pe_start_selfie_path
                ? route('app.patrol-start-selfie', $this->pe_id)
                : null,
            'total_distance' => $this->pe_total_distance,
            'distance' => $this->whenLoaded('routePoints', fn () => $this->distanceSummary()),
            'incident_occurred' => $this->pe_incident_occurred,
            'case_registered' => $this->pe_case_registered,
            'seizure_made' => $this->pe_seizure_made,
            'remarks' => $this->pe_remarks,
            'case_reports' => $this->whenLoaded('caseReports', fn () => $this->caseReports->map(fn ($c) => [
                'id' => $c->pcr_id,
                'case_number' => $c->pcr_case_number,
                'details' => $c->pcr_details,
                'status' => $c->pcr_status,
                'conflict_type' => $c->pcr_conflict_type,
                'rescue_conducted' => $c->pcr_rescue_conducted,
                'species_rescued' => $c->pcr_species_rescued,
                'rehab_details' => $c->pcr_rehab_details,
                'response_time' => $c->pcr_response_time,
                'photos' => $c->relationLoaded('media') ? $c->media->map(fn ($m) => [
                    'id' => $m->pcm_id,
                    'url' => route('app.patrol-case-media', $m->pcm_id),
                ]) : [],
                'photo_count' => $c->relationLoaded('media') ? $c->media->count() : 0,
                'reported_at' => $c->pcr_reported_at?->toISOString(),
            ])),
            'incidents' => $this->whenLoaded('incidents', fn () => $this->incidents->map(fn ($i) => [
                'id' => $i->pi_id,
                'name' => $i->pi_name,
                'details' => $i->pi_details,
                'status' => $i->pi_status,
                'photos' => $i->relationLoaded('media') ? $i->media->map(fn ($m) => [
                    'id' => $m->pim_id,
                    'url' => route('app.patrol-incident-media', $m->pim_id),
                ]) : [],
                'photo_count' => $i->relationLoaded('media') ? $i->media->count() : 0,
                'reported_at' => $i->pi_reported_at?->toISOString(),
            ])),
            'notes' => $this->whenLoaded('notes', fn () => $this->notes->map(fn ($n) => [
                'id' => $n->pn_id,
                'text' => $n->pn_text,
                'created_at' => $n->pn_created_at?->toISOString(),
            ])),
            'custom_field_values' => $this->whenLoaded('customFieldValues', fn () => $this->customFieldValues->map(fn ($v) => [
                'custom_field_id' => $v->pcfv_custom_field_id,
                'field_name' => $v->customField?->rcf_field_name,
                'input_type' => $v->customField?->rcf_input_type,
                'value' => $v->pcfv_value,
            ])),
            'current_travel_mode' => $this->pe_current_travel_mode,
            'current_vehicle_id' => $this->pe_current_vehicle_id,
            'started_at' => $this->pe_started_at?->toISOString(),
            'ended_at' => $this->pe_ended_at?->toISOString(),
            'created_at' => $this->pe_created_at?->toISOString(),
            'updated_at' => $this->pe_updated_at?->toISOString(),
        ];
    }
}
