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
        if (Schema::hasTable('patrol_entry_custom_field_values')) {
            return;
        }

        Schema::create('patrol_entry_custom_field_values', function (Blueprint $table) {
            $table->uuid('pcfv_id')->primary();
            $table->uuid('pcfv_entry_id');
            $table->uuid('pcfv_custom_field_id');
            $table->text('pcfv_value')->nullable();
            $table->timestamp('pcfv_created_at')->nullable();
            $table->timestamp('pcfv_updated_at')->nullable();

            $table->foreign('pcfv_entry_id')->references('pe_id')->on('pe_patrolling_entries')->cascadeOnDelete();
            $table->foreign('pcfv_custom_field_id')->references('rcf_id')->on('range_custom_fields')->cascadeOnDelete();
            $table->unique(['pcfv_entry_id', 'pcfv_custom_field_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrol_entry_custom_field_values');
    }
};
