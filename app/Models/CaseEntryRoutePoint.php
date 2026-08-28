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

    protected static function booted(): void
    {
        static::creating(function (self $point): void {
            $point->cerp_id ??= (string) Str::uuid();
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
