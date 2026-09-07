<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One filled-in instance of a repeatable {@see ActivityReportFieldGroup} on
 * a specific activity — e.g. one "Collected Items" row for "Plastic, 5kg".
 * A ranger adds as many of these as needed while the activity is in
 * progress; each one's field answers live in {@see ActivityReportFieldValue}.
 */
#[Fillable(['arge_id', 'arge_activity_id', 'arge_group_id', 'arge_sort_order'])]
class ActivityReportGroupEntry extends Model
{
    protected $table = 'activity_report_group_entries';

    protected $primaryKey = 'arge_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'arge_created_at';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['arge_sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            $entry->arge_id ??= (string) Str::uuid();
        });
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'arge_activity_id', 'act_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ActivityReportFieldGroup::class, 'arge_group_id', 'arfg_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ActivityReportFieldValue::class, 'arfv_group_entry_id', 'arge_id');
    }
}
