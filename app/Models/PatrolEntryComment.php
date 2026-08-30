<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A comment on a patrol entry — either admin-authored (the admin panel's
 * own running discussion/annotation thread) or, once completed, authored
 * by a ranger with the app-side `comment` permission (see
 * `Roles::APP_FEATURES`) directly from the app. Exactly one of
 * {@see admin}/{@see user} is ever set; separate from a ranger's in-app
 * {@see PatrolNote}, which is queued/synced like any other patrol data
 * rather than posted live. Editable only by the ranger who wrote it (an
 * admin's comment is never editable from the app) — {@see UPDATED_AT}
 * tracks that.
 */
#[Fillable(['pec_id', 'pec_entry_id', 'pec_admin_id', 'pec_user_id', 'pec_text', 'pec_created_at', 'pec_updated_at'])]
class PatrolEntryComment extends Model
{
    use HasFactory;

    protected $table = 'patrol_entry_comments';

    protected $primaryKey = 'pec_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'pec_created_at';

    public const UPDATED_AT = 'pec_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $comment): void {
            $comment->pec_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'pec_created_at' => 'datetime',
            'pec_updated_at' => 'datetime',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PatrollingEntries::class, 'pec_entry_id', 'pe_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'pec_admin_id', 'a_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pec_user_id', 'u_id');
    }
}
