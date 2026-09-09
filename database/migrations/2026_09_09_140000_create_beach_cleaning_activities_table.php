<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A ranger's beach-cleaning drive — deliberately its own module rather
     * than another `activity_categories` entry: its shape (fixed steps,
     * dynamic country/waste-category collection rows, an auto-calculated
     * summary) doesn't fit the generic configurable-report-field engine the
     * `activities` module uses. Starts `in_progress` the moment it's
     * created (step 1) and moves through details/collection/segregation
     * (steps 2-4, each its own PATCH) before being finalized via `submit`
     * (step 5).
     */
    public function up(): void
    {
        if (Schema::hasTable('beach_cleaning_activities')) {
            return;
        }

        Schema::create('beach_cleaning_activities', function (Blueprint $table) {
            $table->uuid('bca_id')->primary();
            $table->string('bca_activity_name');

            $table->uuid('bca_destination_id')->nullable();
            $table->uuid('bca_beach_id')->nullable();
            $table->decimal('bca_latitude', 10, 7)->nullable();
            $table->decimal('bca_longitude', 10, 7)->nullable();

            $table->uuid('bca_created_by');
            $table->unsignedBigInteger('bca_created_via_token_id')->nullable();

            // Step 2 — cleaning details.
            $table->text('bca_summary')->nullable();
            $table->unsignedInteger('bca_participant_count')->nullable();

            // Step 3 — collection.
            $table->unsignedInteger('bca_bags_collected')->nullable();
            $table->decimal('bca_total_weight_kg', 8, 2)->nullable();

            // Step 4 — segregation (the country/category/quantity rows live
            // in their own table; see create_beach_cleaning_segregations_table).
            $table->decimal('bca_segregation_percent', 5, 2)->nullable();

            $table->string('bca_status')->default('in_progress');
            $table->timestamp('bca_submitted_at')->nullable();

            $table->timestamp('bca_created_at')->nullable();
            $table->timestamp('bca_updated_at')->nullable();

            $table->foreign('bca_destination_id')->references('ds_id')->on('destinations')->nullOnDelete();
            $table->foreign('bca_beach_id')->references('bc_id')->on('beaches')->nullOnDelete();
            $table->foreign('bca_created_by')->references('u_id')->on('users')->restrictOnDelete();

            $table->index(['bca_created_by', 'bca_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beach_cleaning_activities');
    }
};
