<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * See {@see PatrolEntryComment} — identical shape/reasoning, for the Case
 * module.
 */
#[Fillable(['cec_id', 'cec_case_id', 'cec_admin_id', 'cec_user_id', 'cec_text', 'cec_created_at', 'cec_updated_at'])]
class CaseEntryComment extends Model
{
    use HasFactory;

    protected $table = 'case_entry_comments';

    protected $primaryKey = 'cec_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'cec_created_at';

    public const UPDATED_AT = 'cec_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $comment): void {
            $comment->cec_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'cec_created_at' => 'datetime',
            'cec_updated_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseEntry::class, 'cec_case_id', 'ce_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'cec_admin_id', 'a_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cec_user_id', 'u_id');
    }
}
