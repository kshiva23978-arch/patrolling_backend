<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['bt_id', 'bt_range_id', 'bt_name', 'bt_status', 'bt_created_at', 'bt_updated_at'])]
class Beats extends Model
{
    use HasFactory;

    protected $table = 'beats';

    protected $primaryKey = 'bt_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'bt_created_at';

    public const UPDATED_AT = 'bt_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $beat): void {
            $beat->bt_id ??= (string) Str::uuid();
        });
    }

    public function range(): BelongsTo
    {
        return $this->belongsTo(Ranges::class, 'bt_range_id', 'rn_id');
    }
}
