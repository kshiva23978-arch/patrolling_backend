<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a ranger (a `users`-table account with the app-side `comment`
     * permission — see `Roles::APP_FEATURES`) post their own comments on a
     * completed patrol, alongside the admin-only comments this table
     * already supported. Exactly one of `pec_admin_id`/`pec_user_id` is
     * ever set per row (enforced in the controller, not the DB); `pec_id`
     * stays the single primary key regardless of which authored it.
     * `pec_updated_at` is new too — the original table had no edit
     * support at all (see `PatrolEntryComment`'s old doc comment).
     */
    public function up(): void
    {
        Schema::table('patrol_entry_comments', function (Blueprint $table) {
            $table->uuid('pec_user_id')->nullable()->after('pec_admin_id');
            $table->timestamp('pec_updated_at')->nullable()->after('pec_created_at');

            $table->foreign('pec_user_id')->references('u_id')->on('users')->restrictOnDelete();
        });

        // `pec_admin_id` was NOT NULL — dropping that constraint needs raw
        // DDL since Doctrine DBAL (Blueprint::change()) isn't available here.
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE patrol_entry_comments MODIFY pec_admin_id CHAR(36) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE patrol_entry_comments ALTER COLUMN pec_admin_id DROP NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite has no ALTER COLUMN; a NOT NULL on a uuid column here
            // was never actually enforced without a CHECK constraint, so
            // there's nothing to relax for local/test sqlite databases.
        }
    }

    public function down(): void
    {
        Schema::table('patrol_entry_comments', function (Blueprint $table) {
            $table->dropForeign(['pec_user_id']);
            $table->dropColumn(['pec_user_id', 'pec_updated_at']);
        });
    }
};
