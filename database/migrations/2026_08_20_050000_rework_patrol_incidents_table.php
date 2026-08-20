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
        // The 2026_08_17_221810 migration that was supposed to create this
        // table is marked as run but the table isn't actually present in
        // this database, so build it fresh here with its full final shape
        // (one entry can now have many incidents, not just one).
        if (! Schema::hasTable('patrol_incidents')) {
            Schema::create('patrol_incidents', function (Blueprint $table) {
                $table->uuid('pi_id')->primary();
                $table->uuid('pi_entry_id');
                $table->uuid('pi_reported_by');
                $table->string('pi_name');
                $table->text('pi_details');
                $table->decimal('pi_latitude', 10, 7)->nullable();
                $table->decimal('pi_longitude', 10, 7)->nullable();
                $table->string('pi_address')->nullable();
                $table->timestamp('pi_reported_at')->nullable();
                $table->timestamp('pi_created_at')->nullable();
                $table->timestamp('pi_updated_at')->nullable();

                $table->foreign('pi_entry_id')->references('pe_id')->on('pe_patrolling_entries')->cascadeOnDelete();
                $table->foreign('pi_reported_by')->references('u_id')->on('users')->restrictOnDelete();
                $table->index(['pi_entry_id', 'pi_reported_at']);
            });
        }

        if (! Schema::hasTable('patrol_incident_media')) {
            Schema::create('patrol_incident_media', function (Blueprint $table) {
                $table->uuid('pim_id')->primary();
                $table->uuid('pim_incident_id');
                $table->string('pim_disk')->default('local');
                $table->string('pim_file_path');
                $table->unsignedInteger('pim_file_size')->nullable();
                $table->decimal('pim_latitude', 10, 7)->nullable();
                $table->decimal('pim_longitude', 10, 7)->nullable();
                $table->timestamp('pim_created_at')->nullable();

                $table->foreign('pim_incident_id')->references('pi_id')->on('patrol_incidents')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrol_incident_media');
        Schema::dropIfExists('patrol_incidents');
    }
};
