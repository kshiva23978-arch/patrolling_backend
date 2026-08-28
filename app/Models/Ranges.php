<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Support\Boundary;

#[Fillable(['rn_id', 'rn_range_id', 'rn_range_name', 'rn_category', 'rn_range_headquarter', 'rn_key_activities', 'rn_created_at', 'rn_updated_at'])]
class Ranges extends Model
{
    use HasFactory;

    public const CATEGORY_HQ = 'HQ Range';

    public const CATEGORY_PROTECTION = 'Protection Range';

    public const CATEGORY_LBWS = 'LBWS';

    public const CATEGORY_MPNP = 'MPNP';

    public const CATEGORY_MGMNP = 'MGMNP';

    public const CATEGORY_RESEARCH_SURVEY = 'Research & Survey';

    /** All recognised range categories. */
    public const CATEGORIES = [
        self::CATEGORY_HQ,
        self::CATEGORY_PROTECTION,
        self::CATEGORY_LBWS,
        self::CATEGORY_MPNP,
        self::CATEGORY_MGMNP,
        self::CATEGORY_RESEARCH_SURVEY,
    ];

    /** Categories that represent an actual protected area (i.e. everything but HQ). */
    public const PROTECTED_AREA_CATEGORIES = [
        self::CATEGORY_PROTECTION,
        self::CATEGORY_LBWS,
        self::CATEGORY_MPNP,
        self::CATEGORY_MGMNP,
        self::CATEGORY_RESEARCH_SURVEY,
    ];

    protected $table = 'ranges';

    protected $primaryKey = 'rn_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'rn_created_at';

    public const UPDATED_AT = 'rn_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $range): void {
            $range->rn_id ??= (string) Str::uuid();
        });
    }

    public function patrollingModes(): BelongsToMany
    {
        return $this->belongsToMany(
            PatrollingModes::class,
            'ranges_patrolling_modes',
            'rpm_range_id',
            'rpm_patrolling_mode_id',
            'rn_id',
            'pm_id'
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_range_access',
            'ura_range_id',
            'ura_user_id',
            'rn_id',
            'u_id'
        );
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(
            Admin::class,
            'admin_range_access',
            'ara_range_id',
            'ara_admin_id',
            'rn_id',
            'a_id'
        );
    }

    public function beats(): HasMany
    {
        return $this->hasMany(Beats::class, 'bt_range_id', 'rn_id');
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(RangeCustomField::class, 'rcf_range_id', 'rn_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicles::class, 'vh_range_id', 'rn_id');
    }

    /** This range's boundary, as a decoded GeoJSON Polygon — `null` if unset. */
    public function boundaryGeoJson(): ?array
    {
        return Boundary::get('ranges', 'rn_id', $this->rn_id, 'rn_boundary');
    }

    /** Sets (or clears, given `null`) this range's boundary. */
    public function setBoundary(?array $geojson): void
    {
        Boundary::set('ranges', 'rn_id', $this->rn_id, 'rn_boundary', $geojson);
    }
}
