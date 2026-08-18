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
        if (Schema::hasTable('patrol_seizures')) {
            return;
        }

        Schema::create('patrol_seizures', function (Blueprint $table) {
            $table->uuid('ps_id')->primary();
            $table->uuid('ps_entry_id')->unique();
            $table->text('ps_details');
            $table->timestamp('ps_created_at')->nullable();
            $table->timestamp('ps_updated_at')->nullable();

            $table->foreign('ps_entry_id')->references('pe_id')->on('pe_patrolling_entries')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrol_seizures');
    }
};
