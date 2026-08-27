<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_participants')) {
            return;
        }

        Schema::create('activity_participants', function (Blueprint $table) {
            $table->uuid('acp_id')->primary();
            $table->uuid('acp_activity_id');
            $table->string('acp_name');
            $table->timestamp('acp_created_at')->nullable();

            $table->foreign('acp_activity_id')->references('act_id')->on('activities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_participants');
    }
};
