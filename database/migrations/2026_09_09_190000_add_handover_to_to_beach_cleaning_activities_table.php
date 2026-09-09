<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Step 6's "Submit Activity" modal: the name of who the collected solid
     * waste was physically handed over to, recorded alongside the closing
     * report at submission time.
     */
    public function up(): void
    {
        if (Schema::hasColumn('beach_cleaning_activities', 'bca_handover_to')) {
            return;
        }

        Schema::table('beach_cleaning_activities', function (Blueprint $table) {
            $table->string('bca_handover_to', 150)->nullable()->after('bca_closing_report');
        });
    }

    public function down(): void
    {
        Schema::table('beach_cleaning_activities', function (Blueprint $table) {
            $table->dropColumn('bca_handover_to');
        });
    }
};
