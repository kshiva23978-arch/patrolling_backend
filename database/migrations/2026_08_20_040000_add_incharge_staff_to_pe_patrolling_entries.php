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
        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->string('pe_incharge_staff')->nullable()->after('pe_staff_names');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->dropColumn('pe_incharge_staff');
        });
    }
};
