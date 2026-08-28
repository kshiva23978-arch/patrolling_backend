<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['a_id', 'a_employee_id', 'a_password_hash', 'a_role_id', 'a_designation_id', 'a_status', 'a_created_at', 'a_updated_at', 'a_last_login'])]
#[Hidden(['a_password_hash', 'remember_token'])]
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $primaryKey = 'a_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'a_created_at';

    public const UPDATED_AT = 'a_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $admin): void {
            $admin->a_id ??= (string) Str::uuid();
        });
    }

    public function getAuthIdentifierName()
    {
        return 'a_employee_id';
    }

    public function getAuthIdentifier()
    {
        return $this->a_employee_id;
    }

    public function getAuthPassword()
    {
        return $this->a_password_hash;
    }

    public function getAuthPasswordName()
    {
        return 'a_password_hash';
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Roles::class, 'a_role_id', 'ro_id');
    }

    public function ranges(): BelongsToMany
    {
        return $this->belongsToMany(
            Ranges::class,
            'admin_range_access',
            'ara_admin_id',
            'ara_range_id',
            'a_id',
            'rn_id'
        );
    }

    /**
     * `true` if this admin is unrestricted — a `master_admin`-level role,
     * or no role at all (see `Roles::hasAdminPermission`'s doc comment for
     * why no-role defaults to unrestricted rather than locked out).
     */
    public function isMasterAdmin(): bool
    {
        return $this->role === null || $this->role->ro_level === Roles::LEVEL_MASTER_ADMIN;
    }

    /**
     * The range ids this admin's *data* is scoped to, or `null` for
     * unrestricted (sees every range) — `null` and `[]` are meaningfully
     * different: `null` means "don't filter at all", `[]` means "filter to
     * nothing" (a department_admin/ranger role with no ranges assigned
     * yet). Only a role with `ro_level` `department_admin` or `ranger`
     * triggers this scoping; every other admin is unrestricted regardless
     * of `ro_permissions` (which separately gates *sections*, not *rows*).
     */
    public function accessibleRangeIds(): ?array
    {
        if (! in_array($this->role?->ro_level, [Roles::LEVEL_DEPARTMENT_ADMIN, Roles::LEVEL_RANGER], true)) {
            return null;
        }

        return $this->ranges()->pluck('rn_id')->all();
    }

    /**
     * `true` if this admin's data-scope includes [$rangeId] — always `true`
     * for an unrestricted admin (see `accessibleRangeIds`).
     */
    public function hasRangeAccess(string $rangeId): bool
    {
        $ids = $this->accessibleRangeIds();

        return $ids === null || in_array($rangeId, $ids, true);
    }

    /**
     * `true` if this admin is allowed [$level] ('view' or 'manage') access
     * to the admin-panel section [$section] — see `Roles::hasAdminPermission`.
     * An admin with no role assigned is unrestricted, same as a role with no
     * `ro_permissions` configured.
     */
    public function hasAdminPermission(string $section, string $level = 'view'): bool
    {
        return $this->role?->hasAdminPermission($section, $level) ?? true;
    }

    /**
     * The `admin` half of this admin's role's permissions, or `null` if
     * unrestricted (no role, or a role with no `ro_permissions` configured)
     * — appended to this model's JSON (see `$appends`) so the Next.js admin
     * panel's session can read what to show/hide without a second request.
     */
    public function getPermissionsAttribute(): ?array
    {
        $rolePermissions = $this->role?->ro_permissions;

        return $rolePermissions['admin'] ?? null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'a_password_hash' => 'hashed',
        ];
    }

    protected $appends = ['permissions'];
}
