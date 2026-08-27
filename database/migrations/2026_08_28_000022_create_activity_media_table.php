<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_media')) {
            return;
        }

        Schema::create('activity_media', function (Blueprint $table) {
            $table->uuid('acm_id')->primary();
            $table->uuid('acm_activity_id');
            $table->string('acm_disk')->default('local');
            $table->string('acm_file_path');
            $table->unsignedBigInteger('acm_file_size')->nullable();
            $table->string('acm_caption')->nullable();
            $table->decimal('acm_latitude', 10, 7)->nullable();
            $table->decimal('acm_longitude', 10, 7)->nullable();
            $table->timestamp('acm_created_at')->nullable();

            $table->foreign('acm_activity_id')->references('act_id')->on('activities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_media');
    }
};
