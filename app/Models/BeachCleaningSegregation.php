<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'bcs_id', 'bcs_activity_id', 'bcs_country_id', 'bcs_waste_category_id',
    'bcs_quantity_kg', 'bcs_weight_kg', 'bcs_created_at', 'bcs_updated_at',
])]
class BeachCleaningSegregation extends Model
{
    use HasFactory;

    protected $table = 'beach_cleaning_segregations';

    protected $primaryKey = 'bcs_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'bcs_created_at';

    public const UPDATED_AT = 'bcs_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $segregation): void {
            $segregation->bcs_id ??= (string) Str::uuid();
        });
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(BeachCleaningActivity::class, 'bcs_activity_id', 'bca_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Countries::class, 'bcs_country_id', 'co_id');
    }

    public function wasteCategory(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class, 'bcs_waste_category_id', 'wc_id');
    }
}
