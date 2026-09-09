<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restores a per-row KG weight to country-wise collection, entered
     * alongside the existing `bcs_quantity_kg` ("No.s") count — the
     * dedicated `beach_cleaning_category_weights` step/table this replaced
     * it with is being removed (see the migration dropping that table),
     * folding weight back into the same country+category row it's actually
     * collected against.
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
