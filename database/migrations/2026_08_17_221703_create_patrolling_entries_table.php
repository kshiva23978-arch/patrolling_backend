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
        if (Schema::hasTable('pe_patrolling_entries')) {
            return;
        }

        Schema::create('pe_patrolling_entries', function (Blueprint $table) {
            $table->uuid('pe_id')->primary();
            $table->string('pe_patrol_id')->unique();
            $table->date('pe_patrol_date');
            $table->time('pe_start_time');
            $table->time('pe_end_time')->nullable();

            $table->uuid('pe_range_id');
            $table->uuid('pe_beat_id')->nullable();
            $table->string('pe_area_covered')->nullable();
            $table->uuid('pe_patrol_type_id');
            $table->uuid('pe_patrol_mode_id');
            $table->uuid('pe_vehicle_id')->nullable();

            $table->decimal('pe_start_latitude', 10, 7)->nullable();
            $table->decimal('pe_start_longitude', 10, 7)->nullable();
            $table->decimal('pe_end_latitude', 10, 7)->nullable();
            $table->decimal('pe_end_longitude', 10, 7)->nullable();

            $table->decimal('pe_start_odometer', 10, 2)->nullable();
            $table->decimal('pe_end_odometer', 10, 2)->nullable();
            $table->decimal('pe_total_distance', 10, 2)
                ->storedAs('pe_end_odometer - pe_start_odometer');

            $table->unsignedInteger('pe_staff_deployed_count')->default(0);
            $table->uuid('pe_patrol_leader_id');
            $table->text('pe_area_patrolled')->nullable();

            $table->boolean('pe_incident_occurred')->default(false);
            $table->boolean('pe_case_registered')->default(false);
            $table->boolean('pe_seizure_made')->default(false);

            $table->text('pe_remarks')->nullable();

            $table->timestamp('pe_created_at')->nullable();
            $table->timestamp('pe_updated_at')->nullable();

            $table->foreign('pe_range_id')->references('rn_id')->on('ranges')->restrictOnDelete();
            $table->foreign('pe_beat_id')->references('bt_id')->on('beats')->nullOnDelete();
            $table->foreign('pe_patrol_type_id')->references('pt_id')->on('patrol_types')->restrictOnDelete();
            $table->foreign('pe_patrol_mode_id')->references('pm_id')->on('patrolling_modes')->restrictOnDelete();
            $table->foreign('pe_vehicle_id')->references('vh_id')->on('vehicles')->nullOnDelete();
            $table->foreign('pe_patrol_leader_id')->references('u_id')->on('users')->restrictOnDelete();

            $table->index('pe_patrol_date');
            $table->index(['pe_range_id', 'pe_patrol_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pe_patrolling_entries');
    }
};
