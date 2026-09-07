<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['ac_id', 'ac_name', 'ac_description', 'ac_has_report', 'ac_created_by'])]
class ActivityCategories extends Model
{
    protected $primaryKey = 'ac_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    protected function casts(): array
    {
        return ['ac_has_report' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            $category->ac_id ??= (string) Str::uuid();
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'ac_created_by', 'a_id');
    }
}
