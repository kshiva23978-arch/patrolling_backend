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
        if (Schema::hasTable('patrol_entry_vehicles')) {
            return;
        }

        Schema::create('patrol_entry_vehicles', function (Blueprint $table) {
            $table->uuid('pev_id')->primary();
            $table->uuid('pev_entry_id');
            $table->uuid('pev_vehicle_id');
            $table->enum('pev_vehicle_type', ['4_wheeler', 'boat']);
            $table->decimal('pev_start_odometer', 10, 2)->nullable();
            $table->decimal('pev_end_odometer', 10, 2)->nullable();
            $table->decimal('pev_distance', 10, 2)
                ->storedAs('pev_end_odometer - pev_start_odometer')
                ->nullable();
            $table->timestamp('pev_created_at')->nullable();
            $table->timestamp('pev_updated_at')->nullable();

            $table->foreign('pev_entry_id')->references('pe_id')->on('pe_patrolling_entries')->cascadeOnDelete();
            $table->foreign('pev_vehicle_id')->references('vh_id')->on('vehicles')->restrictOnDelete();
            $table->index(['pev_entry_id', 'pev_vehicle_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrol_entry_vehicles');
    }
};
