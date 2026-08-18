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
        if (Schema::hasTable('patrol_entry_modes')) {
            return;
        }

        Schema::create('patrol_entry_modes', function (Blueprint $table) {
            $table->uuid('pem_entry_id');
            $table->uuid('pem_patrol_mode_id');

            $table->foreign('pem_entry_id')->references('pe_id')->on('pe_patrolling_entries')->cascadeOnDelete();
            $table->foreign('pem_patrol_mode_id')->references('pm_id')->on('patrolling_modes')->restrictOnDelete();
            $table->primary(['pem_entry_id', 'pem_patrol_mode_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrol_entry_modes');
    }
};
