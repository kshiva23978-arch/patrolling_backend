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
        if (Schema::hasTable('patrol_incidents')) {
            return;
        }

        Schema::create('patrol_incidents', function (Blueprint $table) {
            $table->uuid('pi_id')->primary();
            $table->uuid('pi_entry_id')->unique();
            $table->text('pi_details');
            $table->timestamp('pi_created_at')->nullable();
            $table->timestamp('pi_updated_at')->nullable();

            $table->foreign('pi_entry_id')->references('pe_id')->on('pe_patrolling_entries')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrol_incidents');
    }
};
