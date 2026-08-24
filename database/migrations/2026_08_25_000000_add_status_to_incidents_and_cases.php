<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patrol_incidents', function (Blueprint $table) {
            if (! Schema::hasColumn('patrol_incidents', 'pi_status')) {
                $table->string('pi_status')->default('open')->after('pi_details');
            }
        });

        Schema::table('patrol_case_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('patrol_case_reports', 'pcr_status')) {
                $table->string('pcr_status')->default('open')->after('pcr_details');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patrol_incidents', function (Blueprint $table) {
            if (Schema::hasColumn('patrol_incidents', 'pi_status')) {
                $table->dropColumn('pi_status');
            }
        });

        Schema::table('patrol_case_reports', function (Blueprint $table) {
            if (Schema::hasColumn('patrol_case_reports', 'pcr_status')) {
                $table->dropColumn('pcr_status');
            }
        });
    }
};
