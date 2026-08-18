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
        if (Schema::hasTable('patrol_media')) {
            return;
        }

        Schema::create('patrol_media', function (Blueprint $table) {
            $table->uuid('pmd_id')->primary();
            $table->uuid('pmd_entry_id');
            $table->enum('pmd_type', ['photo', 'video']);
            $table->string('pmd_file_path');
            $table->timestamp('pmd_created_at')->nullable();

            $table->foreign('pmd_entry_id')->references('pe_id')->on('pe_patrolling_entries')->cascadeOnDelete();
            $table->index(['pmd_entry_id', 'pmd_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrol_media');
    }
};
