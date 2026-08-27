<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['acp_id', 'acp_activity_id', 'acp_name', 'acp_created_at'])]
class ActivityParticipant extends Model
{
    use HasFactory;

    protected $table = 'activity_participants';

    protected $primaryKey = 'acp_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'acp_created_at';

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (self $participant): void {
            $participant->acp_id ??= (string) Str::uuid();
        });
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'acp_activity_id', 'act_id');
    }
}
