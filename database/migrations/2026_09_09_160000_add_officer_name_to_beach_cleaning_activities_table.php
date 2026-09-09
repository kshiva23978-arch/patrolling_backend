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
        Schema::table('beach_cleaning_activities', function (Blueprint $table) {
            // Free-text, editable at creation — same relationship
            // `act_conducted_by` has to `act_created_by` on the `activities`
            // table: who actually conducted the drive, which isn't
            // necessarily the ranger logged into the device entering it.
            // Defaults to the logged-in ranger's name client-side, but isn't
            // locked to it.
            $table->string('bca_officer_name')->nullable()->after('bca_beach_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach_cleaning_activities', function (Blueprint $table) {
            $table->dropColumn('bca_officer_name');
        });
    }
};
