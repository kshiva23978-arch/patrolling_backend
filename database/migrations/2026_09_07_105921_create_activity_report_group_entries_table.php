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
        Schema::create('activity_report_group_entries', function (Blueprint $table) {
            $table->uuid('arge_id')->primary();
            $table->uuid('arge_activity_id');
            $table->uuid('arge_group_id');
            $table->unsignedInteger('arge_sort_order')->default(0);
            $table->timestamp('arge_created_at')->nullable();

            $table->foreign('arge_activity_id')->references('act_id')->on('activities')->cascadeOnDelete();
            $table->foreign('arge_group_id')->references('arfg_id')->on('activity_report_field_groups')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_report_group_entries');
    }
};
