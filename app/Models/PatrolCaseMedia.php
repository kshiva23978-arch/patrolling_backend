<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['pcm_id', 'pcm_case_report_id', 'pcm_disk', 'pcm_file_path', 'pcm_file_size', 'pcm_latitude', 'pcm_longitude', 'pcm_created_at'])]
class PatrolCaseMedia extends Model
{
    use HasFactory;

    protected $table = 'patrol_case_media';

    protected $primaryKey = 'pcm_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'pcm_created_at';

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            $media->pcm_id ??= (string) Str::uuid();
        });
    }

    public function caseReport(): BelongsTo
    {
        return $this->belongsTo(PatrolCaseReports::class, 'pcm_case_report_id', 'pcr_id');
    }
}
