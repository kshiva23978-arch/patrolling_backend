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
                'start_odometer' => $v->pev_start_odometer,
                'end_odometer' => $v->pev_end_odometer,
                'distance' => $v->pev_distance,
            ])),
            'deployed_staff' => $this->whenLoaded('deployedStaff', fn () => $this->deployedStaff->map(fn ($u) => [
                'id' => $u->u_id,
                'employee_id' => $u->u_employee_id,
            ])),
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
            'total_distance' => $this->pe_total_distance,
            'incident_occurred' => $this->pe_incident_occurred,
            'case_registered' => $this->pe_case_registered,
            'seizure_made' => $this->pe_seizure_made,
            'remarks' => $this->pe_remarks,
            'ended_at' => $this->pe_ended_at?->toISOString(),
            'created_at' => $this->pe_created_at?->toISOString(),
            'updated_at' => $this->pe_updated_at?->toISOString(),
        ];
    }
}
