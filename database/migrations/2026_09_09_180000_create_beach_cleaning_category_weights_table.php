<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Step 5's "category-wise weight" page: one manually-weighed KG figure
     * per waste category, entered independently of the country-wise
     * collection (step 4, `beach_cleaning_segregations` — a plain count, no
     * weight of its own). A ranger adds at most one row per category.
     */
    public function up(): void
    {
        if (Schema::hasTable('beach_cleaning_category_weights')) {
            return;
        }

        Schema::create('beach_cleaning_category_weights', function (Blueprint $table) {
            $table->uuid('bcw_id')->primary();
            $table->uuid('bcw_activity_id');
            $table->uuid('bcw_waste_category_id')->nullable();
            $table->decimal('bcw_weight_kg', 8, 2);

            $table->timestamp('bcw_created_at')->nullable();
            $table->timestamp('bcw_updated_at')->nullable();

            $table->foreign('bcw_activity_id')->references('bca_id')->on('beach_cleaning_activities')->cascadeOnDelete();
            $table->foreign('bcw_waste_category_id')->references('wc_id')->on('waste_categories')->nullOnDelete();

            $table->index('bcw_activity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beach_cleaning_category_weights');
    }
};
