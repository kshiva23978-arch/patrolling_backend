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
            // Nullable: an activity created before categories existed, or
            // one the ranger just didn't categorize, has no report fields
            // to fill in and keeps using the free-text `act_report` alone.
            $table->uuid('act_category_id')->nullable()->after('act_conducted_by');
            $table->foreign('act_category_id')->references('ac_id')->on('activity_categories')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['act_category_id']);
            $table->dropColumn('act_category_id');
        });
    }
};
