<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'bcw_id', 'bcw_activity_id', 'bcw_waste_category_id',
    'bcw_weight_kg', 'bcw_created_at', 'bcw_updated_at',
])]
class BeachCleaningCategoryWeight extends Model
{
    use HasFactory;

    protected $table = 'beach_cleaning_category_weights';

    protected $primaryKey = 'bcw_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'bcw_created_at';

    public const UPDATED_AT = 'bcw_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $weight): void {
            $weight->bcw_id ??= (string) Str::uuid();
        });
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(BeachCleaningActivity::class, 'bcw_activity_id', 'bca_id');
    }

    public function wasteCategory(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class, 'bcw_waste_category_id', 'wc_id');
    }
}
