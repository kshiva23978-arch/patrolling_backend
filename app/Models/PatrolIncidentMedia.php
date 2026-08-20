<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['pim_id', 'pim_incident_id', 'pim_disk', 'pim_file_path', 'pim_file_size', 'pim_latitude', 'pim_longitude', 'pim_created_at'])]
class PatrolIncidentMedia extends Model
{
    use HasFactory;

    protected $table = 'patrol_incident_media';

    protected $primaryKey = 'pim_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'pim_created_at';

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            $media->pim_id ??= (string) Str::uuid();
        });
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(PatrolIncident::class, 'pim_incident_id', 'pi_id');
    }
}
