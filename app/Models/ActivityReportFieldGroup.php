<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A repeatable section on an activity category's report (e.g. "Collected
 * Items", "Disposed To") — the ranger adds as many entries of this group as
 * needed, each filling in every field under it (see {@see ActivityReportField}).
 */
#[Fillable(['arfg_id', 'arfg_category_id', 'arfg_name', 'arfg_key', 'arfg_sort_order'])]
class ActivityReportFieldGroup extends Model
{
    protected $table = 'activity_report_field_groups';

    protected $primaryKey = 'arfg_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'arfg_created_at';

    public const UPDATED_AT = 'arfg_updated_at';

    protected function casts(): array
    {
        return ['arfg_sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $group): void {
            $group->arfg_id ??= (string) Str::uuid();
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ActivityCategories::class, 'arfg_category_id', 'ac_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ActivityReportField::class, 'arf_group_id', 'arfg_id');
    }
}
