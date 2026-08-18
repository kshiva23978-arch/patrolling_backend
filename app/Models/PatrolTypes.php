<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['pt_id', 'pt_name', 'pt_description', 'pt_status', 'pt_created_at', 'pt_updated_at'])]
class PatrolTypes extends Model
{
    use HasFactory;

    protected $table = 'patrol_types';

    protected $primaryKey = 'pt_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'pt_created_at';

    public const UPDATED_AT = 'pt_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $patrolType): void {
            $patrolType->pt_id ??= (string) Str::uuid();
        });
    }

    /**
     * Range categories allowed to use this patrol type.
     */
    public function categories(): array
    {
        return DB::table('patrol_type_categories')
            ->where('ptc_patrol_type_id', $this->pt_id)
            ->pluck('ptc_category')
            ->all();
    }

    public function isAllowedForCategory(?string $category): bool
    {
        if ($category === null) {
            return true;
        }

        return in_array($category, $this->categories(), true);
    }
}
