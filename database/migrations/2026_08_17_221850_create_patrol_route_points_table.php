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
        if (Schema::hasTable('patrol_route_points')) {
            return;
        }

        Schema::create('patrol_route_points', function (Blueprint $table) {
            $table->uuid('prp_id')->primary();
            $table->uuid('prp_entry_id');
            $table->decimal('prp_latitude', 10, 7);
            $table->decimal('prp_longitude', 10, 7);
            $table->timestamp('prp_recorded_at');

            $table->foreign('prp_entry_id')->references('pe_id')->on('pe_patrolling_entries')->cascadeOnDelete();
            $table->index(['prp_entry_id', 'prp_recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrol_route_points');
    }
};
