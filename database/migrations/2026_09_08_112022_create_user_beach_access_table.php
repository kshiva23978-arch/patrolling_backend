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
        Schema::create('user_beach_access', function (Blueprint $table) {
            $table->uuid('uba_user_id');
            $table->uuid('uba_destination_id');

            $table->foreign('uba_user_id')->references('u_id')->on('users')->cascadeOnDelete();
            $table->foreign('uba_destination_id')->references('ds_id')->on('destinations')->cascadeOnDelete();
            $table->primary(['uba_user_id','uba_destination_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_beach_access');
    }
};
