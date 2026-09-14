<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Per-range, per-year running counters behind every human-readable number
 * the app issues — patrol ids (`PAT-WR-2026-00004`), case numbers and
 * filing numbers (`CASE-WR-2026-00012`), and patrol case-report numbers.
 * Each range keeps its own sequence: Wandoor being on its 4th patrol says
 * nothing about what Chidiyatapu's next patrol is numbered.
 *
 * Plain query-builder rather than the `*NumberSequence` models on purpose:
 * the sequence tables are keyed by (year, range) and Eloquent has no
 * composite-primary-key support — a model-level `increment()` scoped by
 * `pns_year` alone would bump every range's row for that year at once.
 *
 * Must be called inside a transaction (every caller already is) so the
 * `lockForUpdate` actually serializes concurrent submissions for the same
 * range and year.
 */
final class RangeNumberSequence
{
    public const PATROL = ['patrol_number_sequences', 'pns_year', 'pns_range_id', 'pns_last_number'];

    public const CASE_ENTRY = ['case_entry_number_sequences', 'cens_year', 'cens_range_id', 'cens_last_number'];

    public const PATROL_CASE_REPORT = ['case_number_sequences', 'cns_year', 'cns_range_id', 'cns_last_number'];

    /**
     * Issues the next number for [$rangeId] in [$year] from the sequence
     * described by [$sequence] (one of the constants above).
     *
     * @param  array{0: string, 1: string, 2: string, 3: string}  $sequence  table, year column, range column, last-number column
     */
    public static function next(array $sequence, int $year, string $rangeId): int
    {
        [$table, $yearColumn, $rangeColumn, $numberColumn] = $sequence;

        $keys = [$yearColumn => $year, $rangeColumn => $rangeId];

        if (! DB::table($table)->where($keys)->exists()) {
            // insertOrIgnore rather than insert: two first-ever submissions
            // for the same range/year can race past the exists() check, and
            // the loser must not blow up on the primary key — it simply
            // locks the row the winner just created below.
            DB::table($table)->insertOrIgnore($keys + [$numberColumn => 0]);
        }

        $current = DB::table($table)->where($keys)->lockForUpdate()->value($numberColumn);
        $nextNumber = ((int) $current) + 1;

        DB::table($table)->where($keys)->update([$numberColumn => $nextNumber]);

        return $nextNumber;
    }
}
