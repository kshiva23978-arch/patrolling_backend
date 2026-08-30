<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable([
    'pe_id', 'pe_patrol_id', 'pe_type', 'pe_patrol_date', 'pe_start_time', 'pe_end_time',
    'pe_range_id', 'pe_beat_id', 'pe_area_covered', 'pe_patrol_type_id',
    'pe_start_latitude', 'pe_start_longitude', 'pe_end_latitude', 'pe_end_longitude',
    'pe_start_address', 'pe_end_address', 'pe_total_distance',
    'pe_staff_deployed_count', 'pe_staff_names', 'pe_incharge_staff', 'pe_patrol_leader_id',
    'pe_created_via_token_id', 'pe_area_patrolled',
    'pe_incident_occurred', 'pe_case_registered', 'pe_seizure_made', 'pe_remarks',
    'pe_status', 'pe_gps_enabled', 'pe_started_at', 'pe_ended_at',
    'pe_current_travel_mode', 'pe_current_vehicle_id',
    'pe_created_at', 'pe_updated_at',
])]
class PatrollingEntries extends Model
{
    use HasFactory;

    public const TYPE_PATROLLING = 'patrolling';

    public const TYPE_CASE = 'case';

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const TRAVEL_MODE_WALKING = 'walking';

    public const TRAVEL_MODE_VEHICLE = 'vehicle';

    protected $table = 'pe_patrolling_entries';

    protected $primaryKey = 'pe_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'pe_created_at';

    public const UPDATED_AT = 'pe_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            $entry->pe_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'pe_patrol_date' => 'date',
            'pe_incident_occurred' => 'boolean',
            'pe_case_registered' => 'boolean',
            'pe_seizure_made' => 'boolean',
            'pe_gps_enabled' => 'boolean',
            'pe_staff_names' => 'array',
            'pe_started_at' => 'datetime',
            'pe_ended_at' => 'datetime',
        ];
    }

    public function range(): BelongsTo
    {
        return $this->belongsTo(Ranges::class, 'pe_range_id', 'rn_id');
    }

    public function beat(): BelongsTo
    {
        return $this->belongsTo(Beats::class, 'pe_beat_id', 'bt_id');
    }

    public function patrolType(): BelongsTo
    {
        return $this->belongsTo(PatrolTypes::class, 'pe_patrol_type_id', 'pt_id');
    }

    public function patrolLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pe_patrol_leader_id', 'u_id');
    }

    public function modes(): BelongsToMany
    {
        return $this->belongsToMany(
            PatrollingModes::class,
            'patrol_entry_modes',
            'pem_entry_id',
            'pem_patrol_mode_id',
            'pe_id',
            'pm_id'
        );
    }

    public function deployedStaff(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'patrol_entry_staff',
            'pes_entry_id',
            'pes_user_id',
            'pe_id',
            'u_id'
        );
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(PatrolEntryVehicles::class, 'pev_entry_id', 'pe_id');
    }

    public function currentVehicle(): BelongsTo
    {
        return $this->belongsTo(PatrolEntryVehicles::class, 'pe_current_vehicle_id', 'pev_id');
    }

    public function caseReports(): HasMany
    {
        return $this->hasMany(PatrolCaseReports::class, 'pcr_entry_id', 'pe_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(PatrolIncident::class, 'pi_entry_id', 'pe_id');
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(PatrolEntryCustomFieldValue::class, 'pcfv_entry_id', 'pe_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(PatrolNote::class, 'pn_entry_id', 'pe_id')->orderBy('pn_created_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PatrolEntryComment::class, 'pec_entry_id', 'pe_id')->orderBy('pec_created_at');
    }

    public function media(): HasMany
    {
        return $this->hasMany(PatrolMedia::class, 'pmd_entry_id', 'pe_id');
    }

    public function routePoints(): HasMany
    {
        return $this->hasMany(PatrolRoutePoints::class, 'prp_entry_id', 'pe_id')
            ->orderBy('prp_recorded_at');
    }

    /**
     * Distance covered so far, derived from consecutive GPS pings, broken
     * down by the travel mode active when each segment was recorded. Each
     * segment's length is PostGIS's `ST_Distance` over `prp_location`
     * (a geography column — see the `add_postgis_geography_columns`
     * migration) between it and the previous point (`LAG`, ordered the same
     * way as the `routePoints` relation), attributed to *this* point's
     * travel mode, matching the loop this replaced. Runs its own query, so
     * — unlike before — it no longer needs `routePoints` eager-loaded; the
     * `whenLoaded('routePoints')` gate in {@see \App\Http\Resources\PatrolEntryResource}
     * is kept anyway, so list views still don't pay for this.
     *
     * @return array{total_km: float, by_mode: array<string, float>}
     */
    public function distanceSummary(): array
    {
        $rows = DB::select('
            SELECT travel_mode, SUM(segment_m) AS meters
            FROM (
                SELECT
                    COALESCE(prp_travel_mode, \'unknown\') AS travel_mode,
                    ST_Distance(prp_location, LAG(prp_location) OVER (ORDER BY prp_recorded_at)) AS segment_m
                FROM patrol_route_points
                WHERE prp_entry_id = ?
            ) segments
            WHERE segment_m IS NOT NULL
            GROUP BY travel_mode
        ', [$this->pe_id]);

        $totalKm = 0.0;
        $byMode = [];

        foreach ($rows as $row) {
            $km = round(((float) $row->meters) / 1000, 3);
            $byMode[$row->travel_mode] = $km;
            $totalKm += $km;
        }

        return [
            'total_km' => round($totalKm, 3),
            'by_mode' => $byMode,
        ];
    }
}
