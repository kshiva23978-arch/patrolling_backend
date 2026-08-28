<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['cei_id', 'cei_case_id', 'cei_reported_by', 'cei_name', 'cei_details', 'cei_status', 'cei_latitude', 'cei_longitude', 'cei_address', 'cei_reported_at', 'cei_created_at', 'cei_updated_at'])]
class CaseEntryIncident extends Model
{
    use HasFactory;

    protected $table = 'case_entry_incidents';

    protected $primaryKey = 'cei_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'cei_created_at';

    public const UPDATED_AT = 'cei_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $incident): void {
            $incident->cei_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['cei_reported_at' => 'datetime'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseEntry::class, 'cei_case_id', 'ce_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cei_reported_by', 'u_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(CaseEntryIncidentMedia::class, 'ceim_incident_id', 'cei_id');
    }
}
