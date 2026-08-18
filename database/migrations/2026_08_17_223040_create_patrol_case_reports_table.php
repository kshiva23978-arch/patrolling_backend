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
        if (Schema::hasTable('patrol_case_reports')) {
            return;
        }

        Schema::create('patrol_case_reports', function (Blueprint $table) {
            $table->uuid('pcr_id')->primary();
            $table->uuid('pcr_entry_id');
            $table->uuid('pcr_reported_by');
            $table->string('pcr_case_number')->nullable()->unique();
            $table->text('pcr_details');
            $table->decimal('pcr_latitude', 10, 7)->nullable();
            $table->decimal('pcr_longitude', 10, 7)->nullable();
            $table->string('pcr_address')->nullable();
            $table->timestamp('pcr_reported_at');
            $table->timestamp('pcr_created_at')->nullable();
            $table->timestamp('pcr_updated_at')->nullable();

            $table->foreign('pcr_entry_id')->references('pe_id')->on('pe_patrolling_entries')->cascadeOnDelete();
            $table->foreign('pcr_reported_by')->references('u_id')->on('users')->restrictOnDelete();
            $table->index(['pcr_entry_id', 'pcr_reported_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrol_case_reports');
    }
};
