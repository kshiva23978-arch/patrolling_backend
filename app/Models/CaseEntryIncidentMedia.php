<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['ceim_id', 'ceim_incident_id', 'ceim_disk', 'ceim_file_path', 'ceim_file_size', 'ceim_latitude', 'ceim_longitude', 'ceim_created_at'])]
class CaseEntryIncidentMedia extends Model
{
    use HasFactory;

    protected $table = 'case_entry_incident_media';

    protected $primaryKey = 'ceim_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'ceim_created_at';

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            $media->ceim_id ??= (string) Str::uuid();
        });
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(CaseEntryIncident::class, 'ceim_incident_id', 'cei_id');
    }
}
