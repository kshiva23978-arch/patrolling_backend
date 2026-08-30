<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors `patrol_entry_comments`/`case_entry_comments` for the Activity
 * module — see `patrol_entry_comments` for the full reasoning.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_comments')) {
            return;
        }

        Schema::create('activity_comments', function (Blueprint $table) {
            $table->uuid('atc_id')->primary();
            $table->uuid('atc_activity_id');
            $table->uuid('atc_admin_id')->nullable();
            $table->uuid('atc_user_id')->nullable();
            $table->text('atc_text');
            $table->timestamp('atc_created_at')->nullable();
            $table->timestamp('atc_updated_at')->nullable();

            $table->foreign('atc_activity_id')->references('act_id')->on('activities')->cascadeOnDelete();
            $table->foreign('atc_admin_id')->references('a_id')->on('admins')->restrictOnDelete();
            $table->foreign('atc_user_id')->references('u_id')->on('users')->restrictOnDelete();
            $table->index(['atc_activity_id', 'atc_created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_comments');
    }
};
