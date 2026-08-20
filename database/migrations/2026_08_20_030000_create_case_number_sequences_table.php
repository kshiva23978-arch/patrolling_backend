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
        Schema::create('case_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('cns_year')->primary();
            $table->unsignedInteger('cns_last_number')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_number_sequences');
    }
};
