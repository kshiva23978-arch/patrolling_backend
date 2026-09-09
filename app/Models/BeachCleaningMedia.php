<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'bcm_id', 'bcm_activity_id', 'bcm_kind', 'bcm_disk', 'bcm_file_path',
    'bcm_file_size', 'bcm_latitude', 'bcm_longitude', 'bcm_created_at',
])]
class BeachCleaningMedia extends Model
{
    use HasFactory;

    /** Step 2's "before cleaning" photo. */
    public const KIND_BEFORE = 'before';

    /** Step 2's "other photographs" — any number. */
    public const KIND_OTHER = 'other';

    /** Step 3's collection photo. */
    public const KIND_COLLECTION = 'collection';

    public const KINDS = [self::KIND_BEFORE, self::KIND_OTHER, self::KIND_COLLECTION];

    protected $table = 'beach_cleaning_media';

    protected $primaryKey = 'bcm_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'bcm_created_at';

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            $media->bcm_id ??= (string) Str::uuid();
        });
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(BeachCleaningActivity::class, 'bcm_activity_id', 'bca_id');
    }
}
