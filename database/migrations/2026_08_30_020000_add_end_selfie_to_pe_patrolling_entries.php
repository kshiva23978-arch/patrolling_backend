<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->string('pe_end_selfie_disk')->nullable()->after('pe_end_address');
            $table->string('pe_end_selfie_path')->nullable()->after('pe_end_selfie_disk');
        });
    }

    public function down(): void
    {
        Schema::table('pe_patrolling_entries', function (Blueprint $table) {
            $table->dropColumn(['pe_end_selfie_disk', 'pe_end_selfie_path']);
        });
    }
};
