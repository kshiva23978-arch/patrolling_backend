<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['ds_id', 'ds_name', 'ds_status'])]
class Destination extends Model
{
    protected $table = 'destinations';

    protected $primaryKey = 'ds_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'ds_created_at';

    public const UPDATED_AT = 'ds_updated_at';

    protected function casts(): array
    {
        return ['ds_status' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $destination): void {
            $destination->ds_id ??= (string) Str::uuid();
        });
    }

    public function beaches(): HasMany
    {
        return $this->hasMany(Beach::class, 'bc_destination_id', 'ds_id');
    }
}
