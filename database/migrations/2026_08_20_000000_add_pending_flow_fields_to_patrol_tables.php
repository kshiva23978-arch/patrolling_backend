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
            $table->timestamp('pe_started_at')->nullable()->after('pe_status');
        });

        Schema::table('patrol_entry_vehicles', function (Blueprint $table) {
            $table->boolean('pev_is_current')->default(false)->after('pev_vehicle_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->dropColumn('pe_started_at');
        });

        Schema::table('patrol_entry_vehicles', function (Blueprint $table) {
            $table->dropColumn('pev_is_current');
        });
    }
};
