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
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('u_id')->primary();
            $table->string('u_employee_id')->unique();
            $table->string('u_password_hash');
            $table->uuid('u_role_id')->nullable();
            $table->uuid('u_designation_id')->nullable();
            $table->boolean('u_status')->default(true);
            $table->timestamp('u_last_login')->nullable();
            $table->timestamp('u_created_at')->nullable();
            $table->timestamp('u_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
