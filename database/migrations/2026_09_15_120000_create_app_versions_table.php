<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One row per mobile platform describing which build of the field app is
 * current and which is the oldest still allowed to run — the app checks
 * this on launch (see `AppVersionController::show`) and prompts, or
 * forces, an update. Managed from the admin panel by a master admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->uuid('av_id')->primary();
            $table->string('av_platform', 16)->unique(); // android | ios
            // The build currently published on the store, as pubspec's
            // `version: x.y.z+build` — build number is what's compared.
            $table->string('av_latest_version', 32);
            $table->unsignedInteger('av_latest_build');
            // Builds below this are blocked with a mandatory-update screen.
            $table->unsignedInteger('av_min_supported_build')->default(1);
            $table->string('av_update_url', 500)->nullable();
            $table->text('av_release_notes')->nullable();
            $table->timestamp('av_updated_at')->nullable();
            $table->uuid('av_updated_by')->nullable();
        });

        $now = now();
        foreach (['android', 'ios'] as $platform) {
            DB::table('app_versions')->insert([
                'av_id' => (string) Str::uuid(),
                'av_platform' => $platform,
                'av_latest_version' => '1.0.0',
                'av_latest_build' => 1,
                'av_min_supported_build' => 1,
                'av_updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
