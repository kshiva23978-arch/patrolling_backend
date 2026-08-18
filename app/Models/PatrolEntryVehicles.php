<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['pev_id', 'pev_entry_id', 'pev_vehicle_id', 'pev_vehicle_type', 'pev_start_odometer', 'pev_end_odometer', 'pev_created_at', 'pev_updated_at'])]
class PatrolEntryVehicles extends Model
{
    use HasFactory;

    protected $table = 'patrol_entry_vehicles';

    protected $primaryKey = 'pev_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'pev_created_at';

    public const UPDATED_AT = 'pev_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $vehicle): void {
            $vehicle->pev_id ??= (string) Str::uuid();
        });
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PatrollingEntries::class, 'pev_entry_id', 'pe_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicles::class, 'pev_vehicle_id', 'vh_id');
    }
}
