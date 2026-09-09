<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['wc_id', 'wc_name', 'wc_status'])]
class WasteCategory extends Model
{
    protected $table = 'waste_categories';

    protected $primaryKey = 'wc_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'wc_created_at';

    public const UPDATED_AT = 'wc_updated_at';

    protected function casts(): array
    {
        return ['wc_status' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            $category->wc_id ??= (string) Str::uuid();
        });
    }
}
