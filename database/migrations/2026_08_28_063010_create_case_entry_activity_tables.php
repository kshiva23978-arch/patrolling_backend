<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_entry_route_points', function (Blueprint $table) {
            $table->uuid('cerp_id')->primary();
            $table->uuid('cerp_case_id');
            $table->decimal('cerp_latitude', 10, 7);
            $table->decimal('cerp_longitude', 10, 7);
            $table->string('cerp_travel_mode')->nullable();
            $table->uuid('cerp_vehicle_id')->nullable();
            $table->timestamp('cerp_recorded_at');

            $table->foreign('cerp_case_id')->references('ce_id')->on('case_entries')->cascadeOnDelete();
            $table->foreign('cerp_vehicle_id')->references('cev_id')->on('case_entry_vehicles')->nullOnDelete();
            $table->index(['cerp_case_id', 'cerp_recorded_at']);
        });

        Schema::create('case_entry_incidents', function (Blueprint $table) {
            $table->uuid('cei_id')->primary();
            $table->uuid('cei_case_id');
            $table->uuid('cei_reported_by');
            $table->string('cei_name');
            $table->text('cei_details');
            $table->string('cei_status')->default('open');
            $table->decimal('cei_latitude', 10, 7)->nullable();
            $table->decimal('cei_longitude', 10, 7)->nullable();
            $table->string('cei_address')->nullable();
            $table->timestamp('cei_reported_at')->nullable();
            $table->timestamp('cei_created_at')->nullable();
            $table->timestamp('cei_updated_at')->nullable();

            $table->foreign('cei_case_id')->references('ce_id')->on('case_entries')->cascadeOnDelete();
            $table->foreign('cei_reported_by')->references('u_id')->on('users');
        });

        Schema::create('case_entry_incident_media', function (Blueprint $table) {
            $table->uuid('ceim_id')->primary();
            $table->uuid('ceim_incident_id');
            $table->string('ceim_disk')->default('local');
            $table->string('ceim_file_path');
            $table->unsignedBigInteger('ceim_file_size')->nullable();
            $table->decimal('ceim_latitude', 10, 7)->nullable();
            $table->decimal('ceim_longitude', 10, 7)->nullable();
            $table->timestamp('ceim_created_at')->nullable();

            $table->foreign('ceim_incident_id')->references('cei_id')->on('case_entry_incidents')->cascadeOnDelete();
        });

        // The "File Case" sub-action: a rescue/legal filing against a case,
        // same field shape as the patrol module's `patrol_case_reports` but
        // its own table (min-5-photo rule enforced in CaseEntryController,
        // not the schema).
        Schema::create('case_entry_filings', function (Blueprint $table) {
            $table->uuid('cef_id')->primary();
            $table->uuid('cef_case_id');
            $table->uuid('cef_reported_by');
            $table->string('cef_filing_number')->nullable();
            $table->text('cef_details');
            $table->string('cef_status')->default('open');
            $table->string('cef_conflict_type')->nullable();
            $table->boolean('cef_rescue_conducted')->nullable();
            $table->string('cef_species_rescued')->nullable();
            $table->text('cef_rehab_details')->nullable();
            $table->time('cef_response_time')->nullable();
            $table->decimal('cef_latitude', 10, 7);
            $table->decimal('cef_longitude', 10, 7);
            $table->string('cef_address')->nullable();
            $table->timestamp('cef_reported_at')->nullable();
            $table->timestamp('cef_created_at')->nullable();
            $table->timestamp('cef_updated_at')->nullable();

            $table->foreign('cef_case_id')->references('ce_id')->on('case_entries')->cascadeOnDelete();
            $table->foreign('cef_reported_by')->references('u_id')->on('users');
        });

        Schema::create('case_entry_filing_media', function (Blueprint $table) {
            $table->uuid('cefm_id')->primary();
            $table->uuid('cefm_filing_id');
            $table->string('cefm_disk')->default('local');
            $table->string('cefm_file_path');
            $table->unsignedBigInteger('cefm_file_size')->nullable();
            $table->decimal('cefm_latitude', 10, 7)->nullable();
            $table->decimal('cefm_longitude', 10, 7)->nullable();
            $table->timestamp('cefm_created_at')->nullable();

            $table->foreign('cefm_filing_id')->references('cef_id')->on('case_entry_filings')->cascadeOnDelete();
        });

        // Close Case's own mandatory 5 photos — not tied to any incident or
        // filing, just the case's close-out record as a whole.
        Schema::create('case_entry_closing_media', function (Blueprint $table) {
            $table->uuid('cecm_id')->primary();
            $table->uuid('cecm_case_id');
            $table->string('cecm_disk')->default('local');
            $table->string('cecm_file_path');
            $table->unsignedBigInteger('cecm_file_size')->nullable();
            $table->timestamp('cecm_created_at')->nullable();

            $table->foreign('cecm_case_id')->references('ce_id')->on('case_entries')->cascadeOnDelete();
        });

        Schema::create('case_entry_notes', function (Blueprint $table) {
            $table->uuid('cen_id')->primary();
            $table->uuid('cen_case_id');
            $table->uuid('cen_author_id');
            $table->text('cen_text');
            $table->timestamp('cen_created_at')->nullable();

            $table->foreign('cen_case_id')->references('ce_id')->on('case_entries')->cascadeOnDelete();
            $table->foreign('cen_author_id')->references('u_id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_entry_notes');
        Schema::dropIfExists('case_entry_closing_media');
        Schema::dropIfExists('case_entry_filing_media');
        Schema::dropIfExists('case_entry_filings');
        Schema::dropIfExists('case_entry_incident_media');
        Schema::dropIfExists('case_entry_incidents');
        Schema::dropIfExists('case_entry_route_points');
    }
};
