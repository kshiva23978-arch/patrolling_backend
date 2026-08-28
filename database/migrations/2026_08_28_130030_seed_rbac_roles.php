<?php

use App\Models\Roles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Seeds the 5 default roles behind the 4-level RBAC design: 3
     * admin-table levels (`Master Admin` / `Admin` / `Ranger`, keyed by
     * `ro_level`) plus 2 ready-made roles for `users`-table (app) accounts
     * (`Field Staff`, `NGO / Organization`) so a fresh install has a
     * working role to assign on day one instead of admins hand-building
     * `ro_permissions` JSON from scratch. Idempotent by `ro_name` — safe to
     * run again (e.g. re-running migrations in a fresh env) without
     * duplicating or clobbering roles someone has already customized.
     */
    public function up(): void
    {
        $adminSectionSet = function (array $manage, array $viewOnly = []) {
            $sections = [];
            foreach ($manage as $section) {
                $sections[$section] = ['view' => true, 'manage' => true];
            }
            foreach ($viewOnly as $section) {
                $sections[$section] = ['view' => true, 'manage' => false];
            }

            return $sections;
        };

        $this->seedRole('Master Admin', 'Full access to everything.', null, Roles::LEVEL_MASTER_ADMIN);

        $this->seedRole(
            'Admin',
            'Department (range) admin — access limited to their assigned range(s): ranges & reports, patrollings, cases, activities, and activity categories.',
            [
                'admin' => $adminSectionSet(
                    manage: ['patrollings', 'cases', 'activities', 'patrol_types', 'custom_fields', 'user_details'],
                    viewOnly: ['dashboard', 'ranges', 'beats', 'vehicles', 'users', 'designations', 'patrolling_modes'],
                ),
            ],
            Roles::LEVEL_DEPARTMENT_ADMIN,
        );

        $this->seedRole(
            'Ranger',
            'Range admin — access limited to their assigned range: manage beats, staff, vehicles, and activities.',
            [
                'admin' => $adminSectionSet(
                    manage: ['beats', 'users', 'user_details', 'vehicles', 'activities'],
                    viewOnly: ['dashboard', 'ranges', 'patrol_types', 'custom_fields', 'designations', 'patrolling_modes'],
                ),
            ],
            Roles::LEVEL_RANGER,
        );

        $this->seedRole(
            'Field Staff',
            'App-only account for a ranger\'s deployed staff — patrolling, case, and activity features on the field app. No admin panel access.',
            ['app' => ['patrolling' => true, 'case' => true, 'activity' => true]],
            null,
        );

        $this->seedRole(
            'NGO / Organization',
            'App-only account for an NGO/organization conducting activities. No admin panel access — the admin panel shows them only their own conducted activities via their app login.',
            ['app' => ['patrolling' => false, 'case' => false, 'activity' => true]],
            null,
        );
    }

    private function seedRole(string $name, string $description, ?array $permissions, ?string $level): void
    {
        if (Roles::where('ro_name', $name)->exists()) {
            return;
        }

        Roles::create([
            'ro_id' => (string) Str::uuid(),
            'ro_name' => $name,
            'ro_description' => $description,
            'ro_status' => true,
            'ro_permissions' => $permissions,
            'ro_level' => $level,
        ]);
    }

    public function down(): void
    {
        Roles::whereIn('ro_name', ['Master Admin', 'Admin', 'Ranger', 'Field Staff', 'NGO / Organization'])->delete();
    }
};
