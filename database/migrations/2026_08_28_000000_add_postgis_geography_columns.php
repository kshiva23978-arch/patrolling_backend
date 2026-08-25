<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One `geography(Point,4326)` column per existing lat/lng pair, each a
     * `GENERATED ALWAYS ... STORED` column derived from that pair — not a
     * new place the app has to remember to write to. Every existing
     * `->create()`/`->update()` call that sets the `_latitude`/`_longitude`
     * columns keeps working completely unchanged; Postgres recomputes the
     * geography column itself on every write. This is what unlocks real
     * spatial queries (`ST_Distance`, `ST_DWithin`, indexed nearest-point
     * lookups, `ST_Contains` once beat/range boundaries exist) without
     * touching a single Eloquent model's mass-assignment or the API's JSON
     * shape — resources still read the plain `_latitude`/`_longitude`
     * columns exactly as before.
     *
     * A nullable pair (everything except `patrol_route_points`, which is
     * always fully populated) is wrapped in a `CASE` so the geography stays
     * `NULL` rather than the query erroring on a `NULL` argument to
     * `ST_MakePoint`.
     *
     * @var list<array{table: string, lat: string, lng: string, column: string, nullable: bool}>
     */
    private array $points = [
        ['table' => 'patrol_route_points', 'lat' => 'prp_latitude', 'lng' => 'prp_longitude', 'column' => 'prp_location', 'nullable' => false],
        ['table' => 'pe_patrolling_entries', 'lat' => 'pe_start_latitude', 'lng' => 'pe_start_longitude', 'column' => 'pe_start_location', 'nullable' => true],
        ['table' => 'pe_patrolling_entries', 'lat' => 'pe_end_latitude', 'lng' => 'pe_end_longitude', 'column' => 'pe_end_location', 'nullable' => true],
        ['table' => 'patrol_incidents', 'lat' => 'pi_latitude', 'lng' => 'pi_longitude', 'column' => 'pi_location', 'nullable' => true],
        ['table' => 'patrol_incident_media', 'lat' => 'pim_latitude', 'lng' => 'pim_longitude', 'column' => 'pim_location', 'nullable' => true],
        ['table' => 'patrol_case_reports', 'lat' => 'pcr_latitude', 'lng' => 'pcr_longitude', 'column' => 'pcr_location', 'nullable' => true],
        ['table' => 'patrol_case_media', 'lat' => 'pcm_latitude', 'lng' => 'pcm_longitude', 'column' => 'pcm_location', 'nullable' => true],
    ];

    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

        foreach ($this->points as $point) {
            if (! Schema::hasTable($point['table']) || Schema::hasColumn($point['table'], $point['column'])) {
                continue;
            }

            $expression = $point['nullable']
                ? "CASE WHEN {$point['lat']} IS NOT NULL AND {$point['lng']} IS NOT NULL
                        THEN ST_SetSRID(ST_MakePoint({$point['lng']}, {$point['lat']}), 4326)::geography
                   END"
                : "ST_SetSRID(ST_MakePoint({$point['lng']}, {$point['lat']}), 4326)::geography";

            DB::statement("
                ALTER TABLE {$point['table']}
                ADD COLUMN {$point['column']} geography(Point,4326)
                GENERATED ALWAYS AS ({$expression}) STORED
            ");

            DB::statement("
                CREATE INDEX {$point['table']}_{$point['column']}_gix
                ON {$point['table']} USING GIST ({$point['column']})
            ");
        }
    }

    public function down(): void
    {
        foreach ($this->points as $point) {
            if (! Schema::hasTable($point['table']) || ! Schema::hasColumn($point['table'], $point['column'])) {
                continue;
            }

            DB::statement("DROP INDEX IF EXISTS {$point['table']}_{$point['column']}_gix");
            DB::statement("ALTER TABLE {$point['table']} DROP COLUMN {$point['column']}");
        }
    }
};
