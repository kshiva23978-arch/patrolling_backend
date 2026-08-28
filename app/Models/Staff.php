<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A named staff member deployed on Patrolling/Case entries — see the
 * `create_staff_table` migration for how this differs from `User`.
 */
#[Fillable(['st_id', 'st_name', 'st_designation_id', 'st_range_id', 'st_status', 'st_created_at', 'st_updated_at'])]
class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected $primaryKey = 'st_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'st_created_at';

    public const UPDATED_AT = 'st_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $staff): void {
            $staff->st_id ??= (string) Str::uuid();
        });
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designations::class, 'st_designation_id', 'd_id');
    }

    public function range(): BelongsTo
    {
        return $this->belongsTo(Ranges::class, 'st_range_id', 'rn_id');
    }
}
