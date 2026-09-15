<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Route point timestamps were `timestamp(0)` — second resolution — so a
 * queued backlog of pings flushed in a burst (several per second) came back
 * in arbitrary order within each second and the trail zigzagged. Points are
 * now stored with microsecond precision (the app's own queue timestamps
 * carry milliseconds) so "recorded_at" alone orders a trail correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE patrol_route_points ALTER COLUMN prp_recorded_at TYPE timestamp(6) WITHOUT TIME ZONE');
        DB::statement('ALTER TABLE case_entry_route_points ALTER COLUMN cerp_recorded_at TYPE timestamp(6) WITHOUT TIME ZONE');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE patrol_route_points ALTER COLUMN prp_recorded_at TYPE timestamp(0) WITHOUT TIME ZONE');
        DB::statement('ALTER TABLE case_entry_route_points ALTER COLUMN cerp_recorded_at TYPE timestamp(0) WITHOUT TIME ZONE');
    }
};
