<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['pm_id', 'pm_mode_name', 'pm_created_at', 'pm_updated_at'])]
class PatrollingModes extends Model
{
    use HasFactory;

    protected $table = 'patrolling_modes';

    protected $primaryKey = 'pm_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'pm_created_at';

    public const UPDATED_AT = 'pm_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $mode): void {
            $mode->pm_id ??= (string) Str::uuid();
        });
    }

    public function ranges(): BelongsToMany
    {
        return $this->belongsToMany(
            Ranges::class,
            'ranges_patrolling_modes',
            'rpm_patrolling_mode_id',
            'rpm_range_id',
            'pm_id',
            'rn_id'
        );
    }
}
