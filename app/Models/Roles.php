<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['ro_id', 'ro_name', 'ro_description', 'ro_status', 'ro_permissions', 'ro_level', 'ro_created_at', 'ro_updated_at'])]
#[Hidden(['ro_created_at', 'ro_updated_at'])]
class Roles extends Model
{
    /** Unrestricted — same as a `null` role. */
    public const LEVEL_MASTER_ADMIN = 'master_admin';

    /** Scoped to assigned ranges (see `admin_range_access`); broad view/manage within that scope. */
    public const LEVEL_DEPARTMENT_ADMIN = 'department_admin';

    /** Scoped to assigned ranges; narrower section access than `department_admin`. */
    public const LEVEL_RANGER = 'ranger';

    /** Every level an admin-table role's `ro_level` can be. */
    public const ADMIN_LEVELS = [self::LEVEL_MASTER_ADMIN, self::LEVEL_DEPARTMENT_ADMIN, self::LEVEL_RANGER];

    protected $hidden = ['ro_created_at', 'ro_updated_at'];
    use HasFactory, Notifiable, HasApiTokens;

    protected $primaryKey = 'ro_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'ro_created_at';

    public const UPDATED_AT = 'ro_updated_at';

    /** Every admin-panel section a role's permissions can name. */
    public const ADMIN_SECTIONS = [
        'dashboard', 'roles', 'designations', 'patrolling_modes', 'patrol_types',
        'custom_fields', 'patrollings', 'cases', 'activities', 'ranges', 'beats',
        'vehicles', 'staff', 'admins', 'users', 'user_details', 'login_logs',
    ];

    /** Every app-side feature a role's permissions can name. */
    public const APP_FEATURES = ['patrolling', 'case', 'activity', 'comment'];

    protected function casts(): array
    {
        return ['ro_permissions' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $role): void {
            $role->ro_id ??= (string) Str::uuid();
        });
    }

    /**
     * `true` if this role's permissions allow [$level] ('view' or 'manage')
     * on the admin-panel section [$section]. `ro_permissions` (or its
     * `admin` half) being unset means unrestricted — see the migration that
     * added this column for why that's the default rather than locking
     * every existing role out until configured.
     */
    public function hasAdminPermission(string $section, string $level = 'view'): bool
    {
        $admin = $this->ro_permissions['admin'] ?? null;
        if ($admin === null) {
            return true;
        }

        return (bool) ($admin[$section][$level] ?? false);
    }

    /**
     * `true` if this role's permissions allow the app-side [$feature]
     * ('patrolling', 'case', or 'activity'). Same unrestricted-by-default
     * rule as [hasAdminPermission].
     */
    public function hasAppFeature(string $feature): bool
    {
        $app = $this->ro_permissions['app'] ?? null;
        if ($app === null) {
            return true;
        }

        return (bool) ($app[$feature] ?? false);
    }
}
