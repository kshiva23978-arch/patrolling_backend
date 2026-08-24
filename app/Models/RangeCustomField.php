<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An admin-defined field attached to a range, shown dynamically on the
 * "Patrol Report" form (see {@see PatrolEntryCustomFieldValue}) once a
 * ranger has that range's patrol entry in progress.
 */
#[Fillable([
    'rcf_id', 'rcf_range_id', 'rcf_field_name', 'rcf_field_key', 'rcf_input_type',
    'rcf_options', 'rcf_is_required', 'rcf_is_active', 'rcf_sort_order',
    'rcf_created_at', 'rcf_updated_at',
])]
class RangeCustomField extends Model
{
    use HasFactory;

    public const TYPE_TEXT = 'text';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_DROPDOWN = 'dropdown';

    public const TYPE_TIME = 'time';

    public const TYPE_DATE = 'date';

    public const TYPE_NUMBER = 'number';

    /** All recognised input types. */
    public const INPUT_TYPES = [
        self::TYPE_TEXT,
        self::TYPE_BOOLEAN,
        self::TYPE_DROPDOWN,
        self::TYPE_TIME,
        self::TYPE_DATE,
        self::TYPE_NUMBER,
    ];

    protected $table = 'range_custom_fields';

    protected $primaryKey = 'rcf_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'rcf_created_at';

    public const UPDATED_AT = 'rcf_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $field): void {
            $field->rcf_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'rcf_options' => 'array',
            'rcf_is_required' => 'boolean',
            'rcf_is_active' => 'boolean',
            'rcf_sort_order' => 'integer',
        ];
    }

    public function range(): BelongsTo
    {
        return $this->belongsTo(Ranges::class, 'rcf_range_id', 'rn_id');
    }
}
