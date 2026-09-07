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
        Schema::create('activity_report_fields', function (Blueprint $table) {
            $table->uuid('arf_id')->primary();
            $table->uuid('arf_category_id');
        
            $table->uuid('arf_group_id')->nullable();
            $table->string('arf_field_name');
            $table->string('arf_field_key');
            $table->string('arf_input_type');
            $table->json('arf_options')->nullable();
            $table->boolean('arf_is_required')->default(false);
            $table->boolean('arf_is_active')->default(true);
            $table->unsignedInteger('arf_sort_order')->default(0);
            $table->timestamp('arf_created_at')->nullable();
            $table->timestamp('arf_updated_at')->nullable();
        
            $table->foreign('arf_category_id')->references('ac_id')->on('activity_categories')->cascadeOnDelete();
            $table->foreign('arf_group_id')->references('arfg_id')->on('activity_report_field_groups')->cascadeOnDelete();
            $table->unique(['arf_category_id', 'arf_group_id', 'arf_field_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_report_fields');
    }
};
