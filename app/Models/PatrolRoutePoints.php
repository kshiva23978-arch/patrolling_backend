<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['prp_id', 'prp_entry_id', 'prp_latitude', 'prp_longitude', 'prp_travel_mode', 'prp_vehicle_id', 'prp_recorded_at'])]
class PatrolRoutePoints extends Model
{
    use HasFactory;

    protected $table = 'patrol_route_points';

    protected $primaryKey = 'prp_id';

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
            // Time-ordered so an `ORDER BY prp_id` tiebreak follows insertion
            // order rather than random v4 bytes.
            $point->prp_id ??= (string) Str::orderedUuid();
        });
    }

    protected function casts(): array
    {
        return [
            'prp_recorded_at' => 'datetime',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PatrollingEntries::class, 'prp_entry_id', 'pe_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(PatrolEntryVehicles::class, 'prp_vehicle_id', 'pev_id');
    }
}
