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
        if (Schema::hasTable('patrol_entry_staff')) {
            return;
        }

        Schema::create('patrol_entry_staff', function (Blueprint $table) {
            $table->uuid('pes_entry_id');
            $table->uuid('pes_user_id');

            $table->foreign('pes_entry_id')->references('pe_id')->on('pe_patrolling_entries')->cascadeOnDelete();
            $table->foreign('pes_user_id')->references('u_id')->on('users')->cascadeOnDelete();
            $table->primary(['pes_entry_id', 'pes_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patrol_entry_staff');
    }
};
