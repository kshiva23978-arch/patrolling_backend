<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Removes the standalone "category-wise weight" step entirely — its one
     * KG-per-category figure now lives on `beach_cleaning_segregations`
     * itself (`bcs_weight_kg`, added right before this), entered per
     * country+category row instead of as its own independent step.
     */
    public function up(): void
    {
        Schema::dropIfExists('beach_cleaning_category_weights');
    }

    public function down(): void
    {
        Schema::create('beach_cleaning_category_weights', function (\Illuminate\Database\Schema\Blueprint $table) {
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
};
