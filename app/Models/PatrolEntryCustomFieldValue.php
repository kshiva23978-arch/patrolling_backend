<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One filled-in value for a {@see RangeCustomField} on a patrol entry.
 * Always stored as text — {@see RangeCustomField::rcf_input_type} tells the
 * reader how to interpret/format it.
 */
#[Fillable(['pcfv_id', 'pcfv_entry_id', 'pcfv_custom_field_id', 'pcfv_value', 'pcfv_created_at', 'pcfv_updated_at'])]
class PatrolEntryCustomFieldValue extends Model
{
    use HasFactory;

    protected $table = 'patrol_entry_custom_field_values';

    protected $primaryKey = 'pcfv_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'pcfv_created_at';

    public const UPDATED_AT = 'pcfv_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $value): void {
            $value->pcfv_id ??= (string) Str::uuid();
        });
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PatrollingEntries::class, 'pcfv_entry_id', 'pe_id');
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(RangeCustomField::class, 'pcfv_custom_field_id', 'rcf_id');
    }
}
