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
        Schema::table('patrol_route_points', function (Blueprint $table) {
            $table->string('prp_travel_mode')->nullable()->after('prp_longitude');
            $table->uuid('prp_vehicle_id')->nullable()->after('prp_travel_mode');

            $table->foreign('prp_vehicle_id')->references('pev_id')->on('patrol_entry_vehicles')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patrol_route_points', function (Blueprint $table) {
            $table->dropForeign(['prp_vehicle_id']);
            $table->dropColumn(['prp_travel_mode', 'prp_vehicle_id']);
        });
    }
};
