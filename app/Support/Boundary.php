<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Reads and writes a `geography(Polygon,4326)` boundary column (currently
 * `beats.bt_boundary` and `ranges.rn_boundary` — see the
 * `add_boundary_to_beats_and_ranges` migration) via raw SQL rather than
 * Eloquent mass-assignment: a GeoJSON polygon isn't a scalar value Eloquent
 * can just assign, and `ST_GeomFromGeoJSON`/`ST_AsGeoJSON` only make sense
 * as SQL-side conversions.
 */
class Boundary
{
    /**
     * Sets (or, given `null`, clears) [$column] on the row identified by
     * [$idColumn] = [$id]. [$geojson] is a decoded GeoJSON Polygon
     * (`['type' => 'Polygon', 'coordinates' => [...]]`) — validate its
     * shape before calling this, since a malformed one only surfaces as a
     * Postgres error here.
     */
    public static function set(
        string $table,
        string $idColumn,
        string $id,
        string $column,
        ?array $geojson,
    ): void {
        if ($geojson === null) {
            DB::update("UPDATE {$table} SET {$column} = NULL WHERE {$idColumn} = ?", [$id]);

            return;
        }

        DB::update(
            "UPDATE {$table} SET {$column} = ST_GeomFromGeoJSON(?)::geography WHERE {$idColumn} = ?",
            [json_encode($geojson), $id]
        );
    }

    /**
     * [$column] as a decoded GeoJSON Polygon, or `null` if it's unset.
     */
    public static function get(
        string $table,
        string $idColumn,
        string $id,
        string $column,
    ): ?array {
        $row = DB::selectOne(
            "SELECT ST_AsGeoJSON({$column}) AS geojson FROM {$table} WHERE {$idColumn} = ?",
            [$id]
        );

        return $row?->geojson ? json_decode($row->geojson, true) : null;
    }
}
