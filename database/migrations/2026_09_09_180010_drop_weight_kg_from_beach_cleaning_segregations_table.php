<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `bcs_weight_kg` (added right before this) is being replaced by the
     * new `beach_cleaning_category_weights` table — a category's weight is
     * entered independently of country-wise collection, not per
     * country+category row.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('beach_cleaning_segregations', 'bcs_weight_kg')) {
            return;
        }

        Schema::table('beach_cleaning_segregations', function (Blueprint $table) {
            $table->dropColumn('bcs_weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('beach_cleaning_segregations', function (Blueprint $table) {
            $table->decimal('bcs_weight_kg', 8, 2)->nullable()->after('bcs_quantity_kg');
        });
    }
};
