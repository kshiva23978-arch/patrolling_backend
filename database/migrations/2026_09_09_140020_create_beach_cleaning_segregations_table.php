<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row of step 4's "country-wise collection" table: a quantity of
     * one waste category collected that's attributed to one country. A
     * ranger adds as many of these as needed via "+ Add More"; step 5's
     * overall report sums them by category and by country.
     */
    public function up(): void
    {
        if (Schema::hasTable('beach_cleaning_segregations')) {
            return;
        }

        Schema::create('beach_cleaning_segregations', function (Blueprint $table) {
            $table->uuid('bcs_id')->primary();
            $table->uuid('bcs_activity_id');
            $table->uuid('bcs_country_id')->nullable();
            $table->uuid('bcs_waste_category_id')->nullable();
            $table->decimal('bcs_quantity_kg', 8, 2);

            $table->timestamp('bcs_created_at')->nullable();
            $table->timestamp('bcs_updated_at')->nullable();

            $table->foreign('bcs_activity_id')->references('bca_id')->on('beach_cleaning_activities')->cascadeOnDelete();
            // Nullable + nullOnDelete: a country/waste-category later removed
            // from master data must not erase the historical collection
            // figure it was recorded against.
            $table->foreign('bcs_country_id')->references('co_id')->on('countries')->nullOnDelete();
            $table->foreign('bcs_waste_category_id')->references('wc_id')->on('waste_categories')->nullOnDelete();

            $table->index('bcs_activity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beach_cleaning_segregations');
    }
};
