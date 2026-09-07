<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One answer to one {@see ActivityReportField} on one activity — either a
 * flat field's single value ({@see arfv_group_entry_id} null) or one
 * repeatable-group entry's value for that field. A `photo`-type field's
 * answer is a stored file ({@see arfv_file_disk}/{@see arfv_file_path})
 * instead of {@see arfv_value}, same split as {@see ActivityMedia}.
 */
#[Fillable([
    'arfv_id', 'arfv_activity_id', 'arfv_field_id', 'arfv_group_entry_id',
    'arfv_value', 'arfv_file_disk', 'arfv_file_path',
])]
class ActivityReportFieldValue extends Model
{
    protected $table = 'activity_report_field_values';

    protected $primaryKey = 'arfv_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'arfv_created_at';

    public const UPDATED_AT = 'arfv_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $value): void {
            $value->arfv_id ??= (string) Str::uuid();
        });
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'arfv_activity_id', 'act_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ActivityReportField::class, 'arfv_field_id', 'arf_id');
    }

    public function groupEntry(): BelongsTo
    {
        return $this->belongsTo(ActivityReportGroupEntry::class, 'arfv_group_entry_id', 'arge_id');
    }
}
