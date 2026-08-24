<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('pe_patrolling_entries', 'pe_type')) {
                $table->string('pe_type')->default('patrolling')->after('pe_patrol_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            if (Schema::hasColumn('pe_patrolling_entries', 'pe_type')) {
                $table->dropColumn('pe_type');
            }
        });
    }
};
