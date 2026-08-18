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
        if (Schema::hasTable('patrol_type_categories')) {
            return;
        }

        Schema::create('patrol_type_categories', function (Blueprint $table) {
            $table->uuid('ptc_patrol_type_id');
            $table->string('ptc_category');

            $table->foreign('ptc_patrol_type_id')->references('pt_id')->on('patrol_types')->cascadeOnDelete();
            $table->primary(['ptc_patrol_type_id', 'ptc_category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrol_type_categories');
    }
};
