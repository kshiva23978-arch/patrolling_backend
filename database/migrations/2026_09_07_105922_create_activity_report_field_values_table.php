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
        Schema::create('activity_report_field_values', function (Blueprint $table) {
            $table->uuid('arfv_id')->primary();
            $table->uuid('arfv_activity_id');
            $table->uuid('arfv_field_id');
            // Null for a flat field's value; set for a value belonging to
            // one repeatable-group entry (see `activity_report_group_entries`).
            $table->uuid('arfv_group_entry_id')->nullable();
            $table->text('arfv_value')->nullable();
            // Set instead of `arfv_value` for a `photo`-type field.
            $table->string('arfv_file_disk')->nullable();
            $table->string('arfv_file_path')->nullable();
            $table->timestamp('arfv_created_at')->nullable();
            $table->timestamp('arfv_updated_at')->nullable();

            $table->foreign('arfv_activity_id')->references('act_id')->on('activities')->cascadeOnDelete();
            $table->foreign('arfv_field_id')->references('arf_id')->on('activity_report_fields')->cascadeOnDelete();
            $table->foreign('arfv_group_entry_id')->references('arge_id')->on('activity_report_group_entries')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_report_field_values');
    }
};
