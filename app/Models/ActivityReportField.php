<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An admin-defined field on an activity category's report — either a flat,
 * one-off field on the report itself ({@see arf_group_id} null, e.g. "Total
 * Collection (kg)"), or a field within a repeatable group ({@see
 * ActivityReportFieldGroup}, e.g. "Type of Garbage" under "Collected Items").
 */
#[Fillable([
    'arf_id', 'arf_category_id', 'arf_group_id', 'arf_field_name', 'arf_field_key',
    'arf_input_type', 'arf_options', 'arf_is_required', 'arf_is_active', 'arf_sort_order',
])]
class ActivityReportField extends Model
{
    public const TYPE_TEXT = 'text';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_DROPDOWN = 'dropdown';

    public const TYPE_TIME = 'time';

    public const TYPE_DATE = 'date';

    public const TYPE_NUMBER = 'number';

    public const TYPE_PHOTO = 'photo';

    /** All recognised input types. */
    public const INPUT_TYPES = [
        self::TYPE_TEXT,
        self::TYPE_BOOLEAN,
        self::TYPE_DROPDOWN,
        self::TYPE_TIME,
        self::TYPE_DATE,
        self::TYPE_NUMBER,
        self::TYPE_PHOTO,
    ];

    protected $table = 'activity_report_fields';

    protected $primaryKey = 'arf_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'arf_created_at';

    public const UPDATED_AT = 'arf_updated_at';

    protected function casts(): array
    {
        return [
            'arf_options' => 'array',
            'arf_is_required' => 'boolean',
            'arf_is_active' => 'boolean',
            'arf_sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $field): void {
            $field->arf_id ??= (string) Str::uuid();
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ActivityCategories::class, 'arf_category_id', 'ac_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ActivityReportFieldGroup::class, 'arf_group_id', 'arfg_id');
    }
}
