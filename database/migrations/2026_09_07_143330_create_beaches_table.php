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
        Schema::create('beaches', function (Blueprint $table) {
            $table->uuid('bc_id')->primary();
            $table->uuid('bc_destination_id');
            $table->string('bc_name');
            $table->boolean('bc_status')->default(true);
            $table->timestamp('bc_created_at')->nullable();
            $table->timestamp('bc_updated_at')->nullable();

            $table->foreign('bc_destination_id')->references('ds_id')->on('destinations')->cascadeOnDelete();
            $table->unique(['bc_destination_id', 'bc_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beaches');
    }
};
