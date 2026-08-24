<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'pcr_id', 'pcr_entry_id', 'pcr_reported_by', 'pcr_case_number', 'pcr_details', 'pcr_status',
    'pcr_conflict_type', 'pcr_rescue_conducted', 'pcr_species_rescued', 'pcr_rehab_details', 'pcr_response_time',
    'pcr_latitude', 'pcr_longitude', 'pcr_address', 'pcr_reported_at', 'pcr_created_at', 'pcr_updated_at',
])]
class PatrolCaseReports extends Model
{
    use HasFactory;

    protected $table = 'patrol_case_reports';

    protected $primaryKey = 'pcr_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'pcr_created_at';

    public const UPDATED_AT = 'pcr_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $case): void {
            $case->pcr_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'pcr_rescue_conducted' => 'boolean',
            'pcr_reported_at' => 'datetime',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PatrollingEntries::class, 'pcr_entry_id', 'pe_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pcr_reported_by', 'u_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(PatrolCaseMedia::class, 'pcm_case_report_id', 'pcr_id');
    }
}
