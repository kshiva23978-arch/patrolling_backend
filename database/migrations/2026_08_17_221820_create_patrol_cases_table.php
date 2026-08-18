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
        if (Schema::hasTable('patrol_cases')) {
            return;
        }

        Schema::create('patrol_cases', function (Blueprint $table) {
            $table->uuid('pc_id')->primary();
            $table->uuid('pc_entry_id')->unique();
            $table->string('pc_case_number')->unique();
            $table->timestamp('pc_created_at')->nullable();
            $table->timestamp('pc_updated_at')->nullable();

            $table->foreign('pc_entry_id')->references('pe_id')->on('pe_patrolling_entries')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrol_cases');
    }
};
