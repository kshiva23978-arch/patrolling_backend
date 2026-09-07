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
        Schema::create('activity_categories', function (Blueprint $table) {
            $table->uuid('ac_id')->primary();
            $table->string('ac_name')->unique();
            $table->text('ac_description')->nullable();
            $table->boolean('ac_has_report')->default(false);
            $table->uuid('ac_created_by')->nullable();
            $table->foreign('ac_created_by')->references('a_id')->on('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_categories');
    }
};
