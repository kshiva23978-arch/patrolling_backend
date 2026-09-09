<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A photo attached to a beach-cleaning activity. `bcm_kind` records
     * which step it was captured in (step 2's "before" / "other" photos, or
     * step 3's collection photo) so the app can group them back into the
     * right section when re-displaying an in-progress or submitted activity.
     */
    public function up(): void
    {
        if (Schema::hasTable('beach_cleaning_media')) {
            return;
        }

        Schema::create('beach_cleaning_media', function (Blueprint $table) {
            $table->uuid('bcm_id')->primary();
            $table->uuid('bcm_activity_id');
            $table->string('bcm_kind');
            $table->string('bcm_disk')->default('local');
            $table->string('bcm_file_path');
            $table->unsignedBigInteger('bcm_file_size')->nullable();
            $table->decimal('bcm_latitude', 10, 7)->nullable();
            $table->decimal('bcm_longitude', 10, 7)->nullable();
            $table->timestamp('bcm_created_at')->nullable();

            $table->foreign('bcm_activity_id')->references('bca_id')->on('beach_cleaning_activities')->cascadeOnDelete();
            $table->index(['bcm_activity_id', 'bcm_kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beach_cleaning_media');
    }
};
