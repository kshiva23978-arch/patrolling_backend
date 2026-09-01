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
        Schema::create('login_logs', function (Blueprint $table) {
            $table->uuid('ll_id')->primary();
            // 'admin' or 'user' — which table this login was attempted against,
            // matching AuthController::adminLogin/appLogin. Kept as a plain
            // string rather than a real FK since a failed attempt may not
            // resolve to any account at all (see `ll_account_id` below).
            $table->string('ll_account_type');
            // Null when the submitted employee ID matched no account.
            $table->uuid('ll_account_id')->nullable();
            $table->string('ll_employee_id');
            $table->boolean('ll_successful');
            $table->string('ll_ip_address')->nullable();
            $table->string('ll_user_agent')->nullable();
            $table->timestamp('ll_created_at')->nullable();

            $table->index(['ll_account_type', 'll_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_logs');
    }
};
