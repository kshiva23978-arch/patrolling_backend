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
        Schema::table('patrol_case_reports', function (Blueprint $table) {
            $table->string('pcr_conflict_type')->nullable()->after('pcr_details');
            $table->boolean('pcr_rescue_conducted')->nullable()->after('pcr_conflict_type');
            $table->string('pcr_species_rescued')->nullable()->after('pcr_rescue_conducted');
            $table->text('pcr_rehab_details')->nullable()->after('pcr_species_rescued');
            $table->time('pcr_response_time')->nullable()->after('pcr_rehab_details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patrol_case_reports', function (Blueprint $table) {
            $table->dropColumn([
                'pcr_conflict_type',
                'pcr_rescue_conducted',
                'pcr_species_rescued',
                'pcr_rehab_details',
                'pcr_response_time',
            ]);
        });
    }
};
