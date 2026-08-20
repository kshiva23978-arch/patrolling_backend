<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->json('pe_staff_names')->nullable()->after('pe_staff_deployed_count');
        });
    }

    public function down(): void
    {
        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->dropColumn('pe_staff_names');
        });
    }
};
