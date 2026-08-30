<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patrol_entry_comments')) {
            return;
        }

        Schema::create('patrol_entry_comments', function (Blueprint $table) {
            $table->uuid('pec_id')->primary();
            $table->uuid('pec_entry_id');
            $table->uuid('pec_admin_id');
            $table->text('pec_text');
            $table->timestamp('pec_created_at')->nullable();

            $table->foreign('pec_entry_id')->references('pe_id')->on('pe_patrolling_entries')->cascadeOnDelete();
            $table->foreign('pec_admin_id')->references('a_id')->on('admins')->restrictOnDelete();
            $table->index(['pec_entry_id', 'pec_created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_entry_comments');
    }
};
