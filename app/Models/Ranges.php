<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['rn_id', 'rn_range_id', 'rn_range_name', 'rn_range_headquarter', 'rn_key_activities', 'rn_created_at', 'rn_updated_at'])]
class Ranges extends Model
{
    use HasFactory;

    protected $table = 'ranges';

    protected $primaryKey = 'rn_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'rn_created_at';

    public const UPDATED_AT = 'rn_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $range): void {
            $range->rn_id ??= (string) Str::uuid();
        });
    }

    public function patrollingModes(): BelongsToMany
    {
        return $this->belongsToMany(
            PatrollingModes::class,
            'ranges_patrolling_modes',
            'rpm_range_id',
            'rpm_patrolling_mode_id',
            'rn_id',
            'pm_id'
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_range_access',
            'ura_range_id',
            'ura_user_id',
            'rn_id',
            'u_id'
        );
    }
}
