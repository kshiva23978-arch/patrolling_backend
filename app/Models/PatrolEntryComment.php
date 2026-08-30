<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An admin-authored comment on a patrol entry — the admin panel's own
 * running discussion/annotation thread, separate from a ranger's in-app
 * {@see PatrolNote}.
 */
#[Fillable(['pec_id', 'pec_entry_id', 'pec_admin_id', 'pec_text', 'pec_created_at'])]
class PatrolEntryComment extends Model
{
    use HasFactory;

    protected $table = 'patrol_entry_comments';

    protected $primaryKey = 'pec_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'pec_created_at';

    public const UPDATED_AT = null;

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
}
