<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_entries', function (Blueprint $table) {
            $table->string('ce_start_selfie_disk')->nullable()->after('ce_start_address');
            $table->string('ce_start_selfie_path')->nullable()->after('ce_start_selfie_disk');
        });
    }

    public function down(): void
    {
        Schema::table('case_entries', function (Blueprint $table) {
            $table->dropColumn(['ce_start_selfie_disk', 'ce_start_selfie_path']);
        });
    }
};
