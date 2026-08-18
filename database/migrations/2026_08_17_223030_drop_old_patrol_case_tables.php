<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('patrol_incidents');
        Schema::dropIfExists('patrol_cases');
        Schema::dropIfExists('patrol_seizures');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Superseded by patrol_case_reports; not recreated on rollback.
    }
};
