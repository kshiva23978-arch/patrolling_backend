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
        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->string('pe_current_travel_mode')->nullable()->after('pe_started_at');
            $table->uuid('pe_current_vehicle_id')->nullable()->after('pe_current_travel_mode');

            $table->foreign('pe_current_vehicle_id')->references('pev_id')->on('patrol_entry_vehicles')->nullOnDelete();
        });

        Schema::table('patrol_entry_vehicles', function (Blueprint $table) {
            $table->dropColumn('pev_is_current');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patrol_entry_vehicles', function (Blueprint $table) {
            $table->boolean('pev_is_current')->default(false)->after('pev_vehicle_type');
        });

        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->dropForeign(['pe_current_vehicle_id']);
            $table->dropColumn(['pe_current_travel_mode', 'pe_current_vehicle_id']);
        });
    }
};
