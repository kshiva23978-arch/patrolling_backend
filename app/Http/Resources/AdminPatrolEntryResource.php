<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Patrol entry shape for the admin panel's "Patrollings" section — like
 * {@see PatrolEntryResource} but also identifies which ranger is out on the
 * patrol, since admins watch across everyone rather than just their own.
 */
class AdminPatrolEntryResource extends JsonResource
{
    /**
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
            'patrol_type' => $this->whenLoaded('patrolType', fn () => [
                'id' => $this->patrolType->pt_id,
                'name' => $this->patrolType->pt_name,
            ]),
            'patrol_leader' => $this->whenLoaded('patrolLeader', fn () => [
                'id' => $this->patrolLeader->u_id,
                'employee_id' => $this->patrolLeader->u_employee_id,
                'name' => $this->patrolLeader->details?->ud_fullname,
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
                // Postgres `decimal` columns come back from Eloquent as
                // strings (no cast defined on `PatrolEntryVehicles`) — cast
                // to float here so the admin panel can call `.toFixed()`
                // on these directly instead of every caller re-coercing.
                'start_odometer' => $v->pev_start_odometer !== null ? (float) $v->pev_start_odometer : null,
                'end_odometer' => $v->pev_end_odometer !== null ? (float) $v->pev_end_odometer : null,
                'distance' => $v->pev_distance !== null ? (float) $v->pev_distance : null,
            ])),
            'area_covered' => $this->pe_area_covered,
            'area_patrolled' => $this->pe_area_patrolled,
            'remarks' => $this->pe_remarks,
            'staff_deployed_count' => $this->pe_staff_deployed_count,
            'staff_names' => $this->pe_staff_names ?? [],
            'incharge_staff' => $this->pe_incharge_staff,
            'current_travel_mode' => $this->pe_current_travel_mode,
            'current_vehicle_id' => $this->pe_current_vehicle_id,
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
            'total_distance' => $this->pe_total_distance !== null ? (float) $this->pe_total_distance : null,
            'incident_occurred' => $this->pe_incident_occurred,
            'case_registered' => $this->pe_case_registered,
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
                'location' => [
                    'latitude' => $c->pcr_latitude,
                    'longitude' => $c->pcr_longitude,
                    'address' => $c->pcr_address,
                ],
                'photos' => $c->relationLoaded('media')
                    ? $c->media->map(fn ($m) => ['id' => $m->pcm_id])
                    : [],
                'reported_at' => $c->pcr_reported_at?->toISOString(),
            ])),
            'incidents' => $this->whenLoaded('incidents', fn () => $this->incidents->map(fn ($i) => [
                'id' => $i->pi_id,
                'name' => $i->pi_name,
                'details' => $i->pi_details,
                'status' => $i->pi_status,
                'location' => [
                    'latitude' => $i->pi_latitude,
                    'longitude' => $i->pi_longitude,
                    'address' => $i->pi_address,
                ],
                'photos' => $i->relationLoaded('media')
                    ? $i->media->map(fn ($m) => ['id' => $m->pim_id])
                    : [],
                'reported_at' => $i->pi_reported_at?->toISOString(),
            ])),
            'custom_field_values' => $this->whenLoaded('customFieldValues', fn () => $this->customFieldValues->map(fn ($v) => [
                'custom_field_id' => $v->pcfv_custom_field_id,
                'field_name' => $v->customField?->rcf_field_name,
                'input_type' => $v->customField?->rcf_input_type,
                'value' => $v->pcfv_value,
            ])),
            'started_at' => $this->pe_started_at?->toISOString(),
            'ended_at' => $this->pe_ended_at?->toISOString(),
            'created_at' => $this->pe_created_at?->toISOString(),
        ];
    }
}
