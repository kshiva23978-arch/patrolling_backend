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
        if (Schema::hasTable('ranges')) {
            return;
        }

        Schema::create('ranges', function (Blueprint $table) {
            $table->uuid('rn_id')->primary();
            $table->string('rn_range_id')->unique();
            $table->string('rn_range_name')->unique();
            $table->string('rn_range_headquarter');
            $table->text('rn_key_activities')->nullable();
            $table->timestamp('rn_created_at')->nullable();
            $table->timestamp('rn_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ranges');
    }
};
