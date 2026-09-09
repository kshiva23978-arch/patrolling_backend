<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'bca_id', 'bca_activity_name', 'bca_destination_id', 'bca_beach_id', 'bca_officer_name',
    'bca_latitude', 'bca_longitude', 'bca_created_by', 'bca_created_via_token_id',
    'bca_summary', 'bca_participant_count', 'bca_bags_collected', 'bca_total_weight_kg',
    'bca_segregation_percent', 'bca_closing_report', 'bca_handover_to', 'bca_status', 'bca_submitted_at',
    'bca_created_at', 'bca_updated_at',
])]
class BeachCleaningActivity extends Model
{
    use HasFactory;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    protected $table = 'beach_cleaning_activities';

    protected $primaryKey = 'bca_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'bca_created_at';

    public const UPDATED_AT = 'bca_updated_at';

    protected function casts(): array
    {
        return ['bca_submitted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            $activity->bca_id ??= (string) Str::uuid();
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bca_created_by', 'u_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class, 'bca_destination_id', 'ds_id');
    }

    public function beach(): BelongsTo
    {
        return $this->belongsTo(Beach::class, 'bca_beach_id', 'bc_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(BeachCleaningMedia::class, 'bcm_activity_id', 'bca_id')
            ->orderBy('bcm_created_at');
    }

    public function segregations(): HasMany
    {
        return $this->hasMany(BeachCleaningSegregation::class, 'bcs_activity_id', 'bca_id')
            ->orderBy('bcs_created_at');
    }
}
