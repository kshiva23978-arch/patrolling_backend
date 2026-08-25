<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * New capability, not a data migration: no beat or range has ever had a
     * shape before this, so both columns start out `NULL` for every
     * existing row. Ordinary nullable columns (not generated — there's
     * nothing to derive a boundary from) that admins fill in later via
     * {@see \App\Http\Controllers\Api\V1\BeatController} /
     * {@see \App\Http\Controllers\Api\V1\RangeController}, which accept a
     * GeoJSON polygon and convert it with `ST_GeomFromGeoJSON`. Once set,
     * this is what a "is this GPS ping inside the assigned beat/range"
     * geofence check (`ST_Contains`/`ST_Covers`) would run against.
     */
    public function up(): void
    {
        if (Schema::hasTable('beats') && ! Schema::hasColumn('beats', 'bt_boundary')) {
            DB::statement('ALTER TABLE beats ADD COLUMN bt_boundary geography(Polygon,4326) NULL');
            DB::statement('CREATE INDEX beats_bt_boundary_gix ON beats USING GIST (bt_boundary)');
        }

        if (Schema::hasTable('ranges') && ! Schema::hasColumn('ranges', 'rn_boundary')) {
            DB::statement('ALTER TABLE ranges ADD COLUMN rn_boundary geography(Polygon,4326) NULL');
            DB::statement('CREATE INDEX ranges_rn_boundary_gix ON ranges USING GIST (rn_boundary)');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('beats', 'bt_boundary')) {
            DB::statement('DROP INDEX IF EXISTS beats_bt_boundary_gix');
            DB::statement('ALTER TABLE beats DROP COLUMN bt_boundary');
        }

        if (Schema::hasColumn('ranges', 'rn_boundary')) {
            DB::statement('DROP INDEX IF EXISTS ranges_rn_boundary_gix');
            DB::statement('ALTER TABLE ranges DROP COLUMN rn_boundary');
        }
    }
};
