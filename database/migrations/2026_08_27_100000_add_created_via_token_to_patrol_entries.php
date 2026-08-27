<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records which Sanctum access token (one per logged-in device/session
     * — see AuthController::attemptLogin) was used to create each patrol
     * entry, so "you already have an unfinished patrol" (see
     * PatrolEntryController::store()) can be scoped per device instead of
     * per user account: the same ranger is allowed one unfinished patrol on
     * each of several devices, just not two at once from the same one.
     */
    public function up(): void
    {
        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('pe_created_via_token_id')->nullable()->after('pe_patrol_leader_id');

            // nullOnDelete, not restrict/cascade: logging out deletes the
            // token (see AuthController::logout), which must never take the
            // patrol entry down with it — the entry just loses its device
            // association at that point.
            $table->foreign('pe_created_via_token_id')->references('id')->on('personal_access_tokens')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->dropForeign(['pe_created_via_token_id']);
            $table->dropColumn('pe_created_via_token_id');
        });
    }
};
