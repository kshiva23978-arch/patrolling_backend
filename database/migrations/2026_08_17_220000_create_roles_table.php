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
        if (Schema::hasTable('roles')) {
            return;
        }

        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('ro_id')->primary();
            $table->string('ro_name')->unique();
            $table->text('ro_description')->nullable();
            $table->boolean('ro_status')->default(true);
            $table->timestamp('ro_created_at')->nullable();
            $table->timestamp('ro_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
