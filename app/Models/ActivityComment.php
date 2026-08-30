<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * See {@see PatrolEntryComment} — identical shape/reasoning, for the
 * Activity module.
 */
#[Fillable(['atc_id', 'atc_activity_id', 'atc_admin_id', 'atc_user_id', 'atc_text', 'atc_created_at', 'atc_updated_at'])]
class ActivityComment extends Model
{
    use HasFactory;

    protected $table = 'activity_comments';

    protected $primaryKey = 'atc_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'atc_created_at';

    public const UPDATED_AT = 'atc_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $comment): void {
            $comment->atc_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'atc_created_at' => 'datetime',
            'atc_updated_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'atc_activity_id', 'act_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'atc_admin_id', 'a_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atc_user_id', 'u_id');
    }
}
