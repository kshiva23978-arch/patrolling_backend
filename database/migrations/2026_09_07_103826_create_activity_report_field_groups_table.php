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
        Schema::create('activity_report_field_groups', function (Blueprint $table) {
            $table->uuid('arfg_id')->primary();
            $table->uuid('arfg_category_id');
            $table->string('arfg_name');
            $table->string('arfg_key');
            $table->unsignedInteger('arfg_sort_order')->default(0);
            $table->timestamp('arfg_created_at')->nullable();
            $table->timestamp('arfg_updated_at')->nullable();

            $table->foreign('arfg_category_id')->references('ac_id')->on('activity_categories')->cascadeOnDelete();
            $table->unique(['arfg_category_id', 'arfg_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_report_field_groups');
    }
};
