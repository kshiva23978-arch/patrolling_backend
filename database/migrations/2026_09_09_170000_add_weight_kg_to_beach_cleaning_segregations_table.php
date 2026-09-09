<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `bcs_quantity_kg` is a per-category count ("No.s" in the app's UI,
     * despite the column name — see its migration's history), not a real
     * weight. Step 5's "Category-Wise Weight" section needs an actual
     * manually-entered weight per row instead of one derived from that
     * count, hence this separate nullable column.
     */
    public function up(): void
    {
        if (Schema::hasColumn('beach_cleaning_segregations', 'bcs_weight_kg')) {
            return;
        }

        Schema::table('beach_cleaning_segregations', function (Blueprint $table) {
            $table->decimal('bcs_weight_kg', 8, 2)->nullable()->after('bcs_quantity_kg');
        });
    }

    public function down(): void
    {
        Schema::table('beach_cleaning_segregations', function (Blueprint $table) {
            $table->dropColumn('bcs_weight_kg');
        });
    }
};
