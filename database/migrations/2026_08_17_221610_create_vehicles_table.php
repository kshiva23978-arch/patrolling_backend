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
        if (Schema::hasTable('vehicles')) {
            return;
        }

        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('vh_id')->primary();
            $table->uuid('vh_range_id');
            $table->string('vh_registration_number')->unique();
            $table->enum('vh_type', ['vehicle', 'boat'])->default('vehicle');
            $table->boolean('vh_status')->default(true);
            $table->timestamp('vh_created_at')->nullable();
            $table->timestamp('vh_updated_at')->nullable();

            $table->foreign('vh_range_id')->references('rn_id')->on('ranges')->cascadeOnDelete();
            $table->index(['vh_range_id', 'vh_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
