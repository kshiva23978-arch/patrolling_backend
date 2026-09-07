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
            // Nullable for the same reason `act_category_id` is: activities
            // created before this existed have neither.
            $table->uuid('act_destination_id')->nullable()->after('act_category_id');
            $table->uuid('act_beach_id')->nullable()->after('act_destination_id');
            $table->foreign('act_destination_id')->references('ds_id')->on('destinations')->nullOnDelete();
            $table->foreign('act_beach_id')->references('bc_id')->on('beaches')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['act_destination_id']);
            $table->dropForeign(['act_beach_id']);
            $table->dropColumn(['act_destination_id', 'act_beach_id']);
        });
    }
};
