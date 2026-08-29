<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional client-generated id on every "add incident/case report/filing"
     * row — same idempotent-offline-create pattern as `pe_id`/`ce_id` on the
     * entries themselves (see `PatrolEntryController::store`), extended to
     * these sub-records: the app queues one of these locally the moment the
     * ranger taps save, then replays it once connectivity allows, and a slow
     * or dropped response to a replay that actually reached the server must
     * not create a duplicate incident/report/filing.
     */
    public function up(): void
    {
        Schema::table('patrol_incidents', function (Blueprint $table) {
            $table->uuid('pi_client_id')->nullable()->unique()->after('pi_id');
        });

        Schema::table('patrol_case_reports', function (Blueprint $table) {
            $table->uuid('pcr_client_id')->nullable()->unique()->after('pcr_id');
        });

        Schema::table('case_entry_incidents', function (Blueprint $table) {
            $table->uuid('cei_client_id')->nullable()->unique()->after('cei_id');
        });

        Schema::table('case_entry_filings', function (Blueprint $table) {
            $table->uuid('cef_client_id')->nullable()->unique()->after('cef_id');
        });
    }

    public function down(): void
    {
        Schema::table('patrol_incidents', function (Blueprint $table) {
            $table->dropUnique(['pi_client_id']);
            $table->dropColumn('pi_client_id');
        });

        Schema::table('patrol_case_reports', function (Blueprint $table) {
            $table->dropUnique(['pcr_client_id']);
            $table->dropColumn('pcr_client_id');
        });

        Schema::table('case_entry_incidents', function (Blueprint $table) {
            $table->dropUnique(['cei_client_id']);
            $table->dropColumn('cei_client_id');
        });

        Schema::table('case_entry_filings', function (Blueprint $table) {
            $table->dropUnique(['cef_client_id']);
            $table->dropColumn('cef_client_id');
        });
    }
};
