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
        if (Schema::hasTable('waste_categories')) {
            return;
        }

        Schema::create('waste_categories', function (Blueprint $table) {
            $table->uuid('wc_id')->primary();
            $table->string('wc_name')->unique();
            $table->boolean('wc_status')->default(true);
            $table->timestamp('wc_created_at')->nullable();
            $table->timestamp('wc_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waste_categories');
    }
};
