<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['u_id', 'u_employee_id', 'u_password_hash', 'u_role_id', 'u_designation_id', 'u_has_login', 'u_status', 'u_created_at', 'u_updated_at', 'u_last_login'])]
#[Hidden(['u_password_hash', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;
 
    protected $primaryKey = 'u_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'u_created_at';

    public const UPDATED_AT = 'u_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            $user->u_id ??= (string) Str::uuid();
        });
    }

    public function getAuthIdentifierName()
    {
        return 'u_employee_id';
    }

    public function getAuthIdentifier()
    {
        return $this->u_employee_id;
    }

    public function getAuthPassword()
    {
        return $this->u_password_hash;
    }

    public function getAuthPasswordName()
    {
        return 'u_password_hash';
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Roles::class, 'u_role_id', 'ro_id');
    }

    public function details(): HasOne
    {
        return $this->hasOne(UserDetails::class, 'ud_user_id', 'u_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designations::class, 'u_designation_id', 'd_id');
    }

    public function ranges(): BelongsToMany
    {
        return $this->belongsToMany(
            Ranges::class,
            'user_range_access',
            'ura_user_id',
            'ura_range_id',
            'u_id',
            'rn_id'
        );
    }

    /**
     * `true` if this ranger is allowed to use the app-side [$feature]
     * ('patrolling', 'case', or 'activity') — see `Roles::hasAppFeature`. A
     * ranger with no role assigned is unrestricted, same as a role with no
     * `ro_permissions` configured.
     */
    public function hasAppFeature(string $feature): bool
    {
        return $this->role?->hasAppFeature($feature) ?? true;
    }

    /**
     * Every `Roles::APP_FEATURES` flag, resolved through [hasAppFeature] —
     * `null` only when this ranger has no role at all (fully unrestricted).
     * Appended to this model's JSON (see `$appends`) so the Flutter app can
     * read what to show/hide without a second request.
     *
     * Deliberately resolved per-feature rather than returning the role's raw
     * `ro_permissions['app']` array: a role saved before a given feature
     * existed in `Roles::APP_FEATURES` (e.g. `comment`, added after several
     * roles already had `patrolling`/`case`/`activity` configured) simply
     * has no key for it, and [hasAppFeature] treats that missing key inside
     * an otherwise-configured array as `false`. Sending the raw array let
     * the app's own `AppFeatures.fromJson` — which defaults an absent key to
     * `true` — disagree with that: it showed the comment write field for
     * such a role, but every submit still 403'd against the real
     * [hasAppFeature] check. Resolving every key here keeps the two in sync.
     */
    public function getPermissionsAttribute(): ?array
    {
        if ($this->role === null) {
            return null;
        }

        return collect(Roles::APP_FEATURES)
            ->mapWithKeys(fn (string $feature) => [$feature => $this->hasAppFeature($feature)])
            ->all();
    }

    /**
     * This ranger's designation (job title, e.g. "Ranger"/"Field Staff") —
     * appended to this model's JSON (see `$appends`) so the app dashboard
     * can show it without a second request.
     */
    public function getDesignationNameAttribute(): ?string
    {
        return $this->designation?->d_designation_name;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'u_password_hash' => 'hashed',
            'u_has_login' => 'boolean',
        ];
    }

    protected $appends = ['permissions', 'designation_name'];
}
