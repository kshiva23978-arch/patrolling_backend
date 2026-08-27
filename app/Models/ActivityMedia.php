<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'acm_id', 'acm_activity_id', 'acm_disk', 'acm_file_path', 'acm_file_size',
    'acm_caption', 'acm_latitude', 'acm_longitude', 'acm_created_at',
])]
class ActivityMedia extends Model
{
    use HasFactory;

    protected $table = 'activity_media';

    protected $primaryKey = 'acm_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'acm_created_at';

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            $media->acm_id ??= (string) Str::uuid();
        });
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'acm_activity_id', 'act_id');
    }
}
