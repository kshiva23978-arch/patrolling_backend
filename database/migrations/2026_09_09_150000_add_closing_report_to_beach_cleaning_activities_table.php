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
        Schema::table('beach_cleaning_activities', function (Blueprint $table) {
            // Written in the "Submit Activity" modal — a free-text
            // conclusion for the drive, same role as Activity's `act_report`.
            $table->text('bca_closing_report')->nullable()->after('bca_segregation_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach_cleaning_activities', function (Blueprint $table) {
            $table->dropColumn('bca_closing_report');
        });
    }
};
