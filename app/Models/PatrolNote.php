<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A free-text note logged against a patrol entry — quicker than an incident
 * or case report, with no location/photo of its own.
 */
#[Fillable(['pn_id', 'pn_entry_id', 'pn_author_id', 'pn_text', 'pn_created_at'])]
class PatrolNote extends Model
{
    use HasFactory;

    protected $table = 'patrol_notes';

    protected $primaryKey = 'pn_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'pn_created_at';

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (self $note): void {
            $note->pn_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'pn_created_at' => 'datetime',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PatrollingEntries::class, 'pn_entry_id', 'pe_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pn_author_id', 'u_id');
    }
}
