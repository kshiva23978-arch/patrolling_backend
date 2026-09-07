<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['bc_id', 'bc_destination_id', 'bc_name', 'bc_status'])]
class Beach extends Model
{
    protected $table = 'beaches';

    protected $primaryKey = 'bc_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'bc_created_at';

    public const UPDATED_AT = 'bc_updated_at';

    protected function casts(): array
    {
        return ['bc_status' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $beach): void {
            $beach->bc_id ??= (string) Str::uuid();
        });
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class, 'bc_destination_id', 'ds_id');
    }
}
