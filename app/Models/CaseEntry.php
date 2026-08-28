<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A standalone ranger-led investigation/pursuit ("Case") — deliberately
 * independent of the Patrol module (`PatrollingEntries`): its own table, its
 * own lifecycle, no shared row or FK. Named `CaseEntry` rather than bare
 * `Case` (a reserved PHP keyword) — see the `create_case_entries_table`
 * migration's doc comment for the full naming reasoning. User-facing text
 * just says "Case".
 */
#[Fillable([
    'ce_id', 'ce_case_number', 'ce_date', 'ce_start_time', 'ce_end_time',
    'ce_range_id', 'ce_beat_id', 'ce_area_covered', 'ce_case_type',
    'ce_start_latitude', 'ce_start_longitude', 'ce_end_latitude', 'ce_end_longitude',
    'ce_start_address', 'ce_end_address', 'ce_total_distance',
    'ce_staff_deployed_count', 'ce_staff_names', 'ce_incharge_staff', 'ce_leader_id',
    'ce_created_via_token_id', 'ce_current_travel_mode', 'ce_current_vehicle_id',
    'ce_incident_occurred', 'ce_case_filed', 'ce_report',
    'ce_status', 'ce_started_at', 'ce_ended_at', 'ce_created_at', 'ce_updated_at',
])]
class CaseEntry extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const TRAVEL_MODE_WALKING = 'walking';

    public const TRAVEL_MODE_VEHICLE = 'vehicle';

    /** Minimum photos required on Add Incident / File Case / Close Case. */
    public const MIN_PHOTOS = 3;

    protected $table = 'case_entries';

    protected $primaryKey = 'ce_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'ce_created_at';

    public const UPDATED_AT = 'ce_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $case): void {
            $case->ce_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'ce_date' => 'date',
            'ce_incident_occurred' => 'boolean',
            'ce_case_filed' => 'boolean',
            'ce_staff_names' => 'array',
            'ce_started_at' => 'datetime',
            'ce_ended_at' => 'datetime',
        ];
    }

    public function range(): BelongsTo
    {
        return $this->belongsTo(Ranges::class, 'ce_range_id', 'rn_id');
    }

    public function beat(): BelongsTo
    {
        return $this->belongsTo(Beats::class, 'ce_beat_id', 'bt_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ce_leader_id', 'u_id');
    }

    public function modes(): BelongsToMany
    {
        return $this->belongsToMany(
            PatrollingModes::class,
            'case_entry_modes',
            'cem_case_id',
            'cem_patrolling_mode_id',
            'ce_id',
            'pm_id'
        );
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(CaseEntryVehicle::class, 'cev_case_id', 'ce_id');
    }

    public function currentVehicle(): BelongsTo
    {
        return $this->belongsTo(CaseEntryVehicle::class, 'ce_current_vehicle_id', 'cev_id');
    }

    public function routePoints(): HasMany
    {
        return $this->hasMany(CaseEntryRoutePoint::class, 'cerp_case_id', 'ce_id')->orderBy('cerp_recorded_at');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(CaseEntryIncident::class, 'cei_case_id', 'ce_id');
    }

    public function filings(): HasMany
    {
        return $this->hasMany(CaseEntryFiling::class, 'cef_case_id', 'ce_id');
    }

    public function closingMedia(): HasMany
    {
        return $this->hasMany(CaseEntryClosingMedia::class, 'cecm_case_id', 'ce_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CaseEntryNote::class, 'cen_case_id', 'ce_id')->orderBy('cen_created_at');
    }
}
