<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Ranger can add a staff record purely for record-keeping (named
     * staff deployed on a case/patrol, never logging in themselves)
     * without also having to invent login credentials for them — see
     * `UserController`. `u_has_login` gates whether `u_employee_id`/
     * `u_password_hash` are required and whether `AuthController::appLogin`
     * accepts them at all; both columns become nullable so a no-login
     * staff record can leave them blank.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('u_has_login')->default(true)->after('u_designation_id');
        });

        // Avoids a doctrine/dbal dependency (not installed) just for a
        // nullable change — raw DDL instead of Schema::table(...)->change().
        DB::statement('ALTER TABLE users ALTER COLUMN u_employee_id DROP NOT NULL');
        DB::statement('ALTER TABLE users ALTER COLUMN u_password_hash DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('u_has_login');
        });
    }
};
