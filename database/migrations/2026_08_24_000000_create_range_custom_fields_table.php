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
        if (Schema::hasTable('range_custom_fields')) {
            return;
        }

        Schema::create('range_custom_fields', function (Blueprint $table) {
            $table->uuid('rcf_id')->primary();
            $table->uuid('rcf_range_id');
            $table->string('rcf_field_name');
            $table->string('rcf_field_key');
            $table->string('rcf_input_type');
            $table->json('rcf_options')->nullable();
            $table->boolean('rcf_is_required')->default(false);
            $table->boolean('rcf_is_active')->default(true);
            $table->unsignedInteger('rcf_sort_order')->default(0);
            $table->timestamp('rcf_created_at')->nullable();
            $table->timestamp('rcf_updated_at')->nullable();

            $table->foreign('rcf_range_id')->references('rn_id')->on('ranges')->cascadeOnDelete();
            $table->unique(['rcf_range_id', 'rcf_field_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('range_custom_fields');
    }
};
