<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `ro_level` names which of the 4 RBAC levels a role sits at, for
     * roles assigned to `admins` (not `users`) accounts: `master_admin`
     * (unrestricted, same as a `null` role), `department_admin` (scoped to
     * assigned ranges — see `admin_range_access` — with broad view/manage
     * across that scope) or `ranger` (scoped the same way, narrower
     * section access). `null` means "not an admin-table level role" (the
     * default for existing roles, and for roles meant for `users`
     * accounts like field staff or an NGO/organization) — those keep
     * today's behavior of an unrestricted admin, exactly like an admin
     * with no role at all. Only `ro_level` (not `ro_permissions`) decides
     * whether an admin's *data* gets range-filtered; `ro_permissions`
     * still separately decides which *sections* they can see at all.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('ro_level')->nullable()->after('ro_permissions');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('ro_level');
        });
    }
};
