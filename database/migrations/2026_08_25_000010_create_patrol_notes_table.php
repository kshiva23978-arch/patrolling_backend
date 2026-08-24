<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patrol_notes')) {
            return;
        }

        Schema::create('patrol_notes', function (Blueprint $table) {
            $table->uuid('pn_id')->primary();
            $table->uuid('pn_entry_id');
            $table->uuid('pn_author_id');
            $table->text('pn_text');
            $table->timestamp('pn_created_at')->nullable();

            $table->foreign('pn_entry_id')->references('pe_id')->on('pe_patrolling_entries')->cascadeOnDelete();
            $table->foreign('pn_author_id')->references('u_id')->on('users')->restrictOnDelete();
            $table->index(['pn_entry_id', 'pn_created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_notes');
    }
};
