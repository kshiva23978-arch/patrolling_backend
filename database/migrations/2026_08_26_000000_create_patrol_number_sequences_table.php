<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('patrol_number_sequences')) {
            return;
        }

        Schema::create('patrol_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('pns_year')->primary();
            $table->unsignedInteger('pns_last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrol_number_sequences');
    }
};
