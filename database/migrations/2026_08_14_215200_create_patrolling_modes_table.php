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
        if (Schema::hasTable('patrolling_modes')) {
            return;
        }

        Schema::create('patrolling_modes', function (Blueprint $table) {
            $table->uuid('pm_id')->primary();
            $table->string('pm_mode_name')->unique();
            $table->timestamp('pm_created_at')->nullable();
            $table->timestamp('pm_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrolling_modes');
    }
};
