<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['prp_id', 'prp_entry_id', 'prp_latitude', 'prp_longitude', 'prp_recorded_at'])]
class PatrolRoutePoints extends Model
{
    use HasFactory;

    protected $table = 'patrol_route_points';

    protected $primaryKey = 'prp_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $point): void {
            $point->prp_id ??= (string) Str::uuid();
        });
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PatrollingEntries::class, 'prp_entry_id', 'pe_id');
    }
}
