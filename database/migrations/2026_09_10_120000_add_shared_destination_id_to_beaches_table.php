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
        Schema::table('beaches', function (Blueprint $table) {
            // Optionally shares this beach into one other destination's beach
            // list (e.g. Wandoor beach, owned by South Andaman, also shown
            // under Middle Andaman) — deliberately a single nullable column
            // rather than a pivot table since sharing is capped at one extra
            // destination.
            $table->uuid('bc_shared_destination_id')->nullable();

            $table->foreign('bc_shared_destination_id')->references('ds_id')->on('destinations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beaches', function (Blueprint $table) {
            $table->dropForeign(['bc_shared_destination_id']);
            $table->dropColumn('bc_shared_destination_id');
        });
    }
};
