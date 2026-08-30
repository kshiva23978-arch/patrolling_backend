<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'act_id', 'act_name', 'act_description', 'act_latitude', 'act_longitude',
    'act_address', 'act_conducted_by', 'act_created_by', 'act_created_via_token_id', 'act_status',
    'act_report', 'act_started_at', 'act_ended_at',
    'act_created_at', 'act_updated_at',
])]
class Activity extends Model
{
    use HasFactory;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    protected $table = 'activities';

    protected $primaryKey = 'act_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'act_created_at';

    public const UPDATED_AT = 'act_updated_at';

    protected function casts(): array
    {
        return [
            'act_started_at' => 'datetime',
            'act_ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            $activity->act_id ??= (string) Str::uuid();
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'act_created_by', 'u_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ActivityParticipant::class, 'acp_activity_id', 'act_id')
            ->orderBy('acp_created_at');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ActivityMedia::class, 'acm_activity_id', 'act_id')
            ->orderBy('acm_created_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ActivityComment::class, 'atc_activity_id', 'act_id')
            ->orderBy('atc_created_at');
    }
}
