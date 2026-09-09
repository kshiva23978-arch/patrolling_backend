<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['co_id', 'co_country_name', 'co_created_at', 'co_updated_at'])]
class Countries extends Model
{
    use HasFactory;

    protected $table = 'countries';

    protected $primaryKey = 'co_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'co_created_at';

    public const UPDATED_AT = 'co_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $country): void {
            $country->co_id ??= (string) Str::uuid();
        });
    }
}
