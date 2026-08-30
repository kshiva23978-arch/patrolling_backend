<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records which Sanctum access token (one per logged-in device/session
     * — see AuthController::attemptLogin) was used to create each activity,
     * mirroring pe_created_via_token_id/ce_created_via_token_id — so the
     * cross-module "you already have an unfinished patrol/case/activity"
     * rule (ActivityController::store(), PatrolEntryController::store(),
     * CaseEntryController::store()) can be scoped per device instead of per
     * user account: the same ranger is allowed one in-progress item on each
     * of several devices, just not two at once from the same one.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedBigInteger('act_created_via_token_id')->nullable()->after('act_created_by');

            // nullOnDelete, not restrict/cascade: logging out deletes the
            // token (see AuthController::logout), which must never take the
            // activity down with it — it just loses its device association.
            $table->foreign('act_created_via_token_id')->references('id')->on('personal_access_tokens')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['act_created_via_token_id']);
            $table->dropColumn('act_created_via_token_id');
        });
    }
};
