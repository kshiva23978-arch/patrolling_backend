<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A roster of named staff deployed on Patrolling/Case entries — distinct
     * from `users` (login-capable field accounts): this is purely a
     * name+designation reference list a Ranger keeps for their range, with
     * no login of its own. (`users` already supports a no-login staff
     * record via `u_has_login`, for the case where a name in this roster
     * *also* needs field-app access — the two aren't mutually exclusive,
     * just serve different purposes.)
     */
    public function up(): void
    {
        if (Schema::hasTable('staff')) {
            return;
        }

        Schema::create('staff', function (Blueprint $table) {
            $table->uuid('st_id')->primary();
            $table->string('st_name');
            $table->uuid('st_designation_id')->nullable();
            $table->uuid('st_range_id');
            $table->boolean('st_status')->default(true);
            $table->timestamp('st_created_at')->nullable();
            $table->timestamp('st_updated_at')->nullable();

            $table->foreign('st_designation_id')->references('d_id')->on('designations')->nullOnDelete();
            $table->foreign('st_range_id')->references('rn_id')->on('ranges')->cascadeOnDelete();
            $table->index(['st_range_id', 'st_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
