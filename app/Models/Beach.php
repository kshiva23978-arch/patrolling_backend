<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['bc_id', 'bc_destination_id', 'bc_name', 'bc_status', 'bc_shared_destination_id'])]
class Beach extends Model
{
    protected $table = 'beaches';

    protected $primaryKey = 'bc_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'bc_created_at';

    public const UPDATED_AT = 'bc_updated_at';

    protected function casts(): array
    {
        return ['bc_status' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $beach): void {
            $beach->bc_id ??= (string) Str::uuid();
        });
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class, 'bc_destination_id', 'ds_id');
    }

    public function sharedDestination(): BelongsTo
    {
        return $this->belongsTo(Destination::class, 'bc_shared_destination_id', 'ds_id');
    }

    /** Beaches owned by, or shared into, the given destination — a beach's list should be a union of both. */
    public function scopeForDestination(Builder $query, string $destinationId): Builder
    {
        return $query->where(function (Builder $q) use ($destinationId) {
            $q->where('bc_destination_id', $destinationId)
                ->orWhere('bc_shared_destination_id', $destinationId);
        });
    }
}
