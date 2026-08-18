<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['vh_id', 'vh_range_id', 'vh_registration_number', 'vh_type', 'vh_status', 'vh_created_at', 'vh_updated_at'])]
class Vehicles extends Model
{
    use HasFactory;

    protected $table = 'vehicles';

    protected $primaryKey = 'vh_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'vh_created_at';

    public const UPDATED_AT = 'vh_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $vehicle): void {
            $vehicle->vh_id ??= (string) Str::uuid();
        });
    }

    public function range(): BelongsTo
    {
        return $this->belongsTo(Ranges::class, 'vh_range_id', 'rn_id');
    }
}
