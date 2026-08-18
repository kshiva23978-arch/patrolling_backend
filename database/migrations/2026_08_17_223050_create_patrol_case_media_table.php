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
        if (Schema::hasTable('patrol_case_media')) {
            return;
        }

        Schema::create('patrol_case_media', function (Blueprint $table) {
            $table->uuid('pcm_id')->primary();
            $table->uuid('pcm_case_report_id');
            $table->string('pcm_disk')->default('local');
            $table->string('pcm_file_path');
            $table->unsignedBigInteger('pcm_file_size')->nullable();
            $table->decimal('pcm_latitude', 10, 7)->nullable();
            $table->decimal('pcm_longitude', 10, 7)->nullable();
            $table->timestamp('pcm_created_at')->nullable();

            $table->foreign('pcm_case_report_id')->references('pcr_id')->on('patrol_case_reports')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrol_case_media');
    }
};
