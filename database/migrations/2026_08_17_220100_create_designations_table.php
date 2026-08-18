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
        if (Schema::hasTable('designations')) {
            return;
        }

        Schema::create('designations', function (Blueprint $table) {
            $table->uuid('d_id')->primary();
            $table->string('d_designation_name')->unique();
            $table->integer('d_rank_order')->nullable();
            $table->text('d_description')->nullable();
            $table->boolean('d_status')->default(true);
            $table->timestamp('d_created_at')->nullable();
            $table->timestamp('d_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('designations');
    }
};
