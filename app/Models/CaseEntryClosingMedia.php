<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** One of the mandatory closing photos captured on the Close Case page. */
#[Fillable(['cecm_id', 'cecm_case_id', 'cecm_disk', 'cecm_file_path', 'cecm_file_size', 'cecm_created_at'])]
class CaseEntryClosingMedia extends Model
{
    use HasFactory;

    protected $table = 'case_entry_closing_media';

    protected $primaryKey = 'cecm_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'cecm_created_at';

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            $media->cecm_id ??= (string) Str::uuid();
        });
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseEntry::class, 'cecm_case_id', 'ce_id');
    }
}
