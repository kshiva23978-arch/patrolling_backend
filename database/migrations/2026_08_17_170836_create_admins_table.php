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
        Schema::create('admins', function (Blueprint $table) {
            $table->uuid('a_id')->primary();
            $table->string('a_employee_id')->unique();
            $table->string('a_password_hash');
            $table->uuid('a_role_id')->nullable();
            $table->uuid('a_designation_id')->nullable();
            $table->boolean('a_status')->default(true);
            $table->timestamp('a_last_login')->nullable();
            $table->timestamp('a_created_at')->nullable();
            $table->timestamp('a_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
