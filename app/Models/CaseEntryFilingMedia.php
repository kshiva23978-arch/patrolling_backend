<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['cefm_id', 'cefm_filing_id', 'cefm_disk', 'cefm_file_path', 'cefm_file_size', 'cefm_latitude', 'cefm_longitude', 'cefm_created_at'])]
class CaseEntryFilingMedia extends Model
{
    use HasFactory;

    protected $table = 'case_entry_filing_media';

    protected $primaryKey = 'cefm_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'cefm_created_at';

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            $media->cefm_id ??= (string) Str::uuid();
        });
    }

    public function filing(): BelongsTo
    {
        return $this->belongsTo(CaseEntryFiling::class, 'cefm_filing_id', 'cef_id');
    }
}
