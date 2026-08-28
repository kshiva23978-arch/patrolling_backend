<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['cen_id', 'cen_case_id', 'cen_author_id', 'cen_text', 'cen_created_at'])]
class CaseEntryNote extends Model
{
    use HasFactory;

    protected $table = 'case_entry_notes';

    protected $primaryKey = 'cen_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'cen_created_at';

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (self $note): void {
            $note->cen_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['cen_created_at' => 'datetime'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseEntry::class, 'cen_case_id', 'ce_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cen_author_id', 'u_id');
    }
}
