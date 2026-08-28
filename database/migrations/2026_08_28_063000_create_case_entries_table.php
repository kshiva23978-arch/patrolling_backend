<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Case" is a standalone entity — a ranger actively pursuing/investigating
 * a specific case, independent of the Patrol module (`pe_patrolling_entries`
 * and friends). Deliberately not `pe_type='case'` on that table: the two
 * share no row, table, or route.
 *
 * Model/table names use `CaseEntry`/`case_entries` rather than bare `Case` —
 * `case` is a reserved PHP keyword (switch-case), so `class Case` doesn't
 * parse — and `ce_`/`case_entry_*` rather than `case_*` to avoid colliding
 * with the already-existing patrol-linked "case report" concept
 * (`patrol_case_reports`, `CaseNumberSequence`). User-facing text (the
 * Flutter app, API messages) just says "Case" throughout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_entries', function (Blueprint $table) {
            $table->uuid('ce_id')->primary();
            $table->string('ce_case_number')->unique();
            $table->date('ce_date');
            $table->time('ce_start_time');
            $table->time('ce_end_time')->nullable();
            $table->uuid('ce_range_id');
            $table->uuid('ce_beat_id')->nullable();
            $table->string('ce_area_covered')->nullable();
            // Free-text, autocomplete-suggested from prior cases in the same
            // range (see CaseEntryController::fieldSuggestions) — deliberately
            // not a FK to an admin-managed lookup table, unlike patrol type.
            $table->string('ce_case_type');
            $table->decimal('ce_start_latitude', 10, 7)->nullable();
            $table->decimal('ce_start_longitude', 10, 7)->nullable();
            $table->decimal('ce_end_latitude', 10, 7)->nullable();
            $table->decimal('ce_end_longitude', 10, 7)->nullable();
            $table->string('ce_start_address')->nullable();
            $table->string('ce_end_address')->nullable();
            $table->unsignedInteger('ce_staff_deployed_count')->default(0);
            $table->json('ce_staff_names')->nullable();
            $table->string('ce_incharge_staff')->nullable();
            $table->uuid('ce_leader_id');
            $table->unsignedBigInteger('ce_created_via_token_id')->nullable();
            $table->string('ce_current_travel_mode')->nullable();
            $table->uuid('ce_current_vehicle_id')->nullable();
            $table->decimal('ce_total_distance', 10, 3)->nullable();
            $table->boolean('ce_incident_occurred')->default(false);
            $table->boolean('ce_case_filed')->default(false);
            // The Close Case report — separate from any individual filing's
            // own details, this is the ranger's closing summary of the case.
            $table->text('ce_report')->nullable();
            $table->string('ce_status')->default('pending'); // pending -> in_progress -> completed
            $table->timestamp('ce_started_at')->nullable();
            $table->timestamp('ce_ended_at')->nullable();
            $table->timestamp('ce_created_at')->nullable();
            $table->timestamp('ce_updated_at')->nullable();

            $table->foreign('ce_range_id')->references('rn_id')->on('ranges');
            $table->foreign('ce_beat_id')->references('bt_id')->on('beats')->nullOnDelete();
            $table->foreign('ce_leader_id')->references('u_id')->on('users');
            $table->index(['ce_leader_id', 'ce_status']);
        });

        Schema::create('case_entry_modes', function (Blueprint $table) {
            $table->uuid('cem_case_id');
            $table->uuid('cem_patrolling_mode_id');
            $table->primary(['cem_case_id', 'cem_patrolling_mode_id']);

            $table->foreign('cem_case_id')->references('ce_id')->on('case_entries')->cascadeOnDelete();
            $table->foreign('cem_patrolling_mode_id')->references('pm_id')->on('patrolling_modes')->cascadeOnDelete();
        });

        Schema::create('case_entry_vehicles', function (Blueprint $table) {
            $table->uuid('cev_id')->primary();
            $table->uuid('cev_case_id');
            $table->uuid('cev_vehicle_id');
            $table->string('cev_vehicle_type');
            $table->decimal('cev_start_odometer', 10, 2)->nullable();
            $table->decimal('cev_end_odometer', 10, 2)->nullable();
            // Nullable: correctly NULL until cev_end_odometer is set (Close
            // Case) — Laravel's storedAs() doesn't infer nullability from
            // the expression, so this must be explicit or every insert
            // before the vehicle has an end reading fails NOT NULL.
            $table->decimal('cev_distance', 10, 3)->nullable()->storedAs('cev_end_odometer - cev_start_odometer');
            $table->timestamp('cev_created_at')->nullable();
            $table->timestamp('cev_updated_at')->nullable();

            $table->foreign('cev_case_id')->references('ce_id')->on('case_entries')->cascadeOnDelete();
            $table->foreign('cev_vehicle_id')->references('vh_id')->on('vehicles');
        });

        Schema::table('case_entries', function (Blueprint $table) {
            $table->foreign('ce_current_vehicle_id')->references('cev_id')->on('case_entry_vehicles')->nullOnDelete();
        });

        Schema::create('case_entry_number_sequences', function (Blueprint $table) {
            $table->smallInteger('cens_year')->primary();
            $table->integer('cens_last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('case_entries', function (Blueprint $table) {
            $table->dropForeign(['ce_current_vehicle_id']);
        });
        Schema::dropIfExists('case_entry_number_sequences');
        Schema::dropIfExists('case_entry_vehicles');
        Schema::dropIfExists('case_entry_modes');
        Schema::dropIfExists('case_entries');
    }
};
