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
        if (Schema::hasTable('beats')) {
            return;
        }

        Schema::create('beats', function (Blueprint $table) {
            $table->uuid('bt_id')->primary();
            $table->uuid('bt_range_id');
            $table->string('bt_name');
            $table->boolean('bt_status')->default(true);
            $table->timestamp('bt_created_at')->nullable();
            $table->timestamp('bt_updated_at')->nullable();

            $table->foreign('bt_range_id')->references('rn_id')->on('ranges')->cascadeOnDelete();
            $table->unique(['bt_range_id', 'bt_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beats');
    }
};
