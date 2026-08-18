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
            $table->dropForeign(['pe_vehicle_id']);
            $table->dropForeign(['pe_patrol_mode_id']);
        });

        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->dropColumn('pe_total_distance');
        });

        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->dropColumn([
                'pe_vehicle_id',
                'pe_patrol_mode_id',
                'pe_start_odometer',
                'pe_end_odometer',
            ]);
        });

        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->string('pe_status')->default('in_progress')->after('pe_seizure_made');
            $table->boolean('pe_gps_enabled')->default(false)->after('pe_status');
            $table->string('pe_start_address')->nullable()->after('pe_start_longitude');
            $table->string('pe_end_address')->nullable()->after('pe_end_longitude');
            $table->decimal('pe_total_distance', 10, 2)->nullable()->after('pe_end_address');
            $table->timestamp('pe_ended_at')->nullable()->after('pe_total_distance');

            $table->index('pe_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->dropColumn([
                'pe_status',
                'pe_gps_enabled',
                'pe_start_address',
                'pe_end_address',
                'pe_total_distance',
                'pe_ended_at',
            ]);
        });

        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->uuid('pe_vehicle_id')->nullable();
            $table->uuid('pe_patrol_mode_id')->nullable();
            $table->decimal('pe_start_odometer', 10, 2)->nullable();
            $table->decimal('pe_end_odometer', 10, 2)->nullable();
            $table->decimal('pe_total_distance', 10, 2)->nullable();

            $table->foreign('pe_vehicle_id')->references('vh_id')->on('vehicles')->nullOnDelete();
            $table->foreign('pe_patrol_mode_id')->references('pm_id')->on('patrolling_modes')->restrictOnDelete();
        });
    }
};
