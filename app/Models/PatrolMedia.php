<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['pmd_id', 'pmd_entry_id', 'pmd_type', 'pmd_file_path', 'pmd_created_at'])]
class PatrolMedia extends Model
{
    use HasFactory;

    protected $table = 'patrol_media';

    protected $primaryKey = 'pmd_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'pmd_created_at';

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            $media->pmd_id ??= (string) Str::uuid();
        });
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PatrollingEntries::class, 'pmd_entry_id', 'pe_id');
    }
}
