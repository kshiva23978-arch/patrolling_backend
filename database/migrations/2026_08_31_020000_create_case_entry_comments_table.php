<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors `patrol_entry_comments` (as of the migration adding
 * `pec_user_id`) for the Case module — see that table for the full
 * reasoning. Both author columns are nullable; exactly one is set per row,
 * enforced in the controller rather than the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('case_entry_comments')) {
            return;
        }

        Schema::create('case_entry_comments', function (Blueprint $table) {
            $table->uuid('cec_id')->primary();
            $table->uuid('cec_case_id');
            $table->uuid('cec_admin_id')->nullable();
            $table->uuid('cec_user_id')->nullable();
            $table->text('cec_text');
            $table->timestamp('cec_created_at')->nullable();
            $table->timestamp('cec_updated_at')->nullable();

            $table->foreign('cec_case_id')->references('ce_id')->on('case_entries')->cascadeOnDelete();
            $table->foreign('cec_admin_id')->references('a_id')->on('admins')->restrictOnDelete();
            $table->foreign('cec_user_id')->references('u_id')->on('users')->restrictOnDelete();
            $table->index(['cec_case_id', 'cec_created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_entry_comments');
    }
};
