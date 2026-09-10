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
        // 'gps' = a real fix, 'dr' = a dead-reckoned fallback point recorded
        // by the app while GPS was unavailable (dense canopy, urban canyon)
        // — see the Flutter app's `DeadReckoningService`. Defaults to 'gps'
        // so every point recorded before this column existed is correctly
        // treated as a real fix.
        Schema::table('patrol_route_points', function (Blueprint $table) {
            $table->string('prp_source')->default('gps')->after('prp_longitude');
        });

        Schema::table('case_entry_route_points', function (Blueprint $table) {
            $table->string('cerp_source')->default('gps')->after('cerp_longitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patrol_route_points', function (Blueprint $table) {
            $table->dropColumn('prp_source');
        });

        Schema::table('case_entry_route_points', function (Blueprint $table) {
            $table->dropColumn('cerp_source');
        });
    }
};
