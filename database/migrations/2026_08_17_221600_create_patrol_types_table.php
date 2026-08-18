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
        if (Schema::hasTable('patrol_types')) {
            return;
        }

        Schema::create('patrol_types', function (Blueprint $table) {
            $table->uuid('pt_id')->primary();
            $table->string('pt_name')->unique();
            $table->text('pt_description')->nullable();
            $table->boolean('pt_status')->default(true);
            $table->timestamp('pt_created_at')->nullable();
            $table->timestamp('pt_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrol_types');
    }
};
