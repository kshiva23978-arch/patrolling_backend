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
        if (Schema::hasTable('user_range_access')) {
            return;
        }

        Schema::create('user_range_access', function (Blueprint $table) {
            $table->uuid('ura_user_id');
            $table->uuid('ura_range_id');

            $table->foreign('ura_user_id')->references('u_id')->on('users')->cascadeOnDelete();
            $table->foreign('ura_range_id')->references('rn_id')->on('ranges')->cascadeOnDelete();
            $table->primary(['ura_user_id', 'ura_range_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_range_access');
    }
};
