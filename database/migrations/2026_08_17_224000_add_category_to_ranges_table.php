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
        Schema::table('ranges', function (Blueprint $table) {
            $table->string('rn_category')->nullable()->after('rn_range_name');
            $table->index('rn_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ranges', function (Blueprint $table) {
            $table->dropColumn('rn_category');
        });
    }
};
