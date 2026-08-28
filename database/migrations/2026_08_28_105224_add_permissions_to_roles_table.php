<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Null means "unrestricted" — every existing role (and every
            // admin/user with no role at all) keeps today's behavior of
            // full access until someone explicitly configures a role's
            // permissions, so this rolls out without retroactively locking
            // anyone out. Shape: {"admin": {"<section>": {"view": bool,
            // "manage": bool}, ...}, "app": {"patrolling"|"case"|"activity": bool}}.
            $table->json('ro_permissions')->nullable()->after('ro_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('ro_permissions');
        });
    }
};
