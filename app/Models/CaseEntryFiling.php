<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** The "File Case" sub-action: a rescue/legal filing recorded against a Case. */
#[Fillable([
    'cef_id', 'cef_client_id', 'cef_case_id', 'cef_reported_by', 'cef_filing_number', 'cef_details', 'cef_status',
    'cef_conflict_type', 'cef_rescue_conducted', 'cef_species_rescued', 'cef_rehab_details', 'cef_response_time',
    'cef_latitude', 'cef_longitude', 'cef_address', 'cef_reported_at', 'cef_created_at', 'cef_updated_at',
])]
class CaseEntryFiling extends Model
{
    use HasFactory;

    protected $table = 'case_entry_filings';

    protected $primaryKey = 'cef_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'cef_created_at';

    public const UPDATED_AT = 'cef_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $filing): void {
            $filing->cef_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'cef_rescue_conducted' => 'boolean',
            'cef_reported_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseEntry::class, 'cef_case_id', 'ce_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cef_reported_by', 'u_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(CaseEntryFilingMedia::class, 'cefm_filing_id', 'cef_id');
    }
}
