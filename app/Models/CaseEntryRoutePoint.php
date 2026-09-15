<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['cerp_id', 'cerp_case_id', 'cerp_latitude', 'cerp_longitude', 'cerp_travel_mode', 'cerp_vehicle_id', 'cerp_recorded_at'])]
class CaseEntryRoutePoint extends Model
{
    use HasFactory;

    protected $table = 'case_entry_route_points';

    protected $primaryKey = 'cerp_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    // Keep the microseconds the app sends in `recorded_at` (Laravel's
    // default 'Y-m-d H:i:s' would silently drop them on write, collapsing
    // a burst of backlog pings onto the same second and losing their order).
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected static function booted(): void
    {
        static::creating(function (self $point): void {
            // Time-ordered so an `ORDER BY cerp_id` tiebreak follows insertion
            // order rather than random v4 bytes.
            $point->cerp_id ??= (string) Str::orderedUuid();
        });
    }

    protected function casts(): array
    {
        return ['cerp_recorded_at' => 'datetime'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseEntry::class, 'cerp_case_id', 'ce_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(CaseEntryVehicle::class, 'cerp_vehicle_id', 'cev_id');
    }
}
