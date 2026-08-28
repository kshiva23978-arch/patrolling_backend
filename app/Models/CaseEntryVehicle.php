<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['cev_id', 'cev_case_id', 'cev_vehicle_id', 'cev_vehicle_type', 'cev_start_odometer', 'cev_end_odometer', 'cev_created_at', 'cev_updated_at'])]
class CaseEntryVehicle extends Model
{
    use HasFactory;

    protected $table = 'case_entry_vehicles';

    protected $primaryKey = 'cev_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'cev_created_at';

    public const UPDATED_AT = 'cev_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $vehicle): void {
            $vehicle->cev_id ??= (string) Str::uuid();
        });
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseEntry::class, 'cev_case_id', 'ce_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicles::class, 'cev_vehicle_id', 'vh_id');
    }
}
