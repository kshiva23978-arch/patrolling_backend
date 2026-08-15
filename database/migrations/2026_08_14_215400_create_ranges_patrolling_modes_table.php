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
        if (Schema::hasTable('ranges_patrolling_modes')) {
            return;
        }

        Schema::create('ranges_patrolling_modes', function (Blueprint $table) {
            $table->uuid('rpm_range_id');
            $table->uuid('rpm_patrolling_mode_id');

            $table->foreign('rpm_range_id')->references('rn_id')->on('ranges')->cascadeOnDelete();
            $table->foreign('rpm_patrolling_mode_id')->references('pm_id')->on('patrolling_modes')->cascadeOnDelete();
            $table->primary(['rpm_range_id', 'rpm_patrolling_mode_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ranges_patrolling_modes');
    }
};
