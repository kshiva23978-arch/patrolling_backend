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
        Schema::table('activities', function (Blueprint $table) {
            // A ranger now enters a headcount directly instead of naming
            // each participant (see the now-unused `activity_participants`
            // table/endpoints, kept for backward compatibility with old data).
            $table->unsignedInteger('act_participant_count')->nullable()->after('act_conducted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('act_participant_count');
        });
    }
};
