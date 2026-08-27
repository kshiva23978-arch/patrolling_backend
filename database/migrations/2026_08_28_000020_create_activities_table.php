<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Standalone field activity (a survey, awareness drive, plantation,
     * etc.) — deliberately not tied to a patrol entry or a range, unlike
     * cases/incidents. Starts `in_progress` the moment it's created (there's
     * no separate "start" step the way a patrol has) and is closed out with
     * a report/conclusion via {@see \App\Http\Controllers\Api\V1\ActivityController::end}.
     */
    public function up(): void
    {
        if (Schema::hasTable('activities')) {
            return;
        }

        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('act_id')->primary();
            $table->string('act_name');
            $table->text('act_description')->nullable();

            $table->decimal('act_latitude', 10, 7)->nullable();
            $table->decimal('act_longitude', 10, 7)->nullable();
            $table->string('act_address')->nullable();

            $table->string('act_conducted_by');

            $table->uuid('act_created_by');
            $table->string('act_status')->default('in_progress');

            $table->text('act_report')->nullable();

            $table->timestamp('act_started_at')->nullable();
            $table->timestamp('act_ended_at')->nullable();

            $table->timestamp('act_created_at')->nullable();
            $table->timestamp('act_updated_at')->nullable();

            $table->foreign('act_created_by')->references('u_id')->on('users')->restrictOnDelete();

            $table->index(['act_created_by', 'act_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
