<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Patrol ids, case numbers, and patrol case-report numbers used to draw from
 * one counter per year shared by every range, so Wandoor's 4th patrol and
 * Chidiyatapu's 1st could come out as PAT-WR-2026-00004 and
 * PAT-CT-2026-00005. Each range now keeps its own sequence per year: the
 * three sequence tables gain a range id in their primary key, and their
 * counters are re-seeded from the highest number already issued to each
 * range, so nothing already numbered is ever reused.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rebuild(
            'patrol_number_sequences',
            'pns_year',
            'pns_range_id',
            'pns_last_number',
            // Existing patrols — keyed by the range they were created for and
            // the year of their patrol date (see generatePatrolId).
            DB::table('pe_patrolling_entries')
                ->select('pe_range_id as range_id', 'pe_patrol_id as number', 'pe_patrol_date as dated_at')
                ->whereNotNull('pe_patrol_id')
                ->get(),
        );

        $this->rebuild(
            'case_entry_number_sequences',
            'cens_year',
            'cens_range_id',
            'cens_last_number',
            // Case numbers *and* filing numbers share this sequence (see
            // CaseEntryController::generateCaseNumber), both issued in the
            // year they were created.
            DB::table('case_entries')
                ->select('ce_range_id as range_id', 'ce_case_number as number', 'ce_created_at as dated_at')
                ->whereNotNull('ce_case_number')
                ->get()
                ->concat(
                    DB::table('case_entry_filings')
                        ->join('case_entries', 'case_entries.ce_id', '=', 'case_entry_filings.cef_case_id')
                        ->select('case_entries.ce_range_id as range_id', 'case_entry_filings.cef_filing_number as number', 'case_entry_filings.cef_created_at as dated_at')
                        ->whereNotNull('case_entry_filings.cef_filing_number')
                        ->get()
                ),
        );

        $this->rebuild(
            'case_number_sequences',
            'cns_year',
            'cns_range_id',
            'cns_last_number',
            // Case reports filed during a patrol — the patrol's range.
            DB::table('patrol_case_reports')
                ->join('pe_patrolling_entries', 'pe_patrolling_entries.pe_id', '=', 'patrol_case_reports.pcr_entry_id')
                ->select('pe_patrolling_entries.pe_range_id as range_id', 'patrol_case_reports.pcr_case_number as number', 'patrol_case_reports.pcr_created_at as dated_at')
                ->whereNotNull('patrol_case_reports.pcr_case_number')
                ->get(),
        );
    }

    public function down(): void
    {
        // Collapses each per-range counter back into one per year, keeping
        // the highest number so the global sequence never reissues one.
        foreach ([
            ['patrol_number_sequences', 'pns_year', 'pns_range_id', 'pns_last_number'],
            ['case_entry_number_sequences', 'cens_year', 'cens_range_id', 'cens_last_number'],
            ['case_number_sequences', 'cns_year', 'cns_range_id', 'cns_last_number'],
        ] as [$table, $yearColumn, $rangeColumn, $numberColumn]) {
            $rows = DB::table($table)
                ->select($yearColumn, DB::raw("MAX({$numberColumn}) as last_number"))
                ->groupBy($yearColumn)
                ->get();

            Schema::dropIfExists($table);
            Schema::create($table, function (Blueprint $t) use ($yearColumn, $numberColumn) {
                $t->unsignedSmallInteger($yearColumn)->primary();
                $t->unsignedInteger($numberColumn)->default(0);
            });

            foreach ($rows as $row) {
                DB::table($table)->insert([
                    $yearColumn => $row->{$yearColumn},
                    $numberColumn => $row->last_number,
                ]);
            }
        }
    }

    /**
     * Recreates [$table] keyed by (year, range) and seeds one row per
     * (range, year) actually seen in [$issued] with the highest trailing
     * number already handed out there. The old global rows are discarded —
     * they only ever counted across all ranges, which is exactly what's
     * being replaced.
     *
     * @param  Collection<int, object{range_id: ?string, number: ?string, dated_at: mixed}>  $issued
     */
    private function rebuild(string $table, string $yearColumn, string $rangeColumn, string $numberColumn, $issued): void
    {
        Schema::dropIfExists($table);
        Schema::create($table, function (Blueprint $t) use ($yearColumn, $rangeColumn, $numberColumn) {
            $t->unsignedSmallInteger($yearColumn);
            $t->uuid($rangeColumn);
            $t->unsignedInteger($numberColumn)->default(0);
            $t->primary([$yearColumn, $rangeColumn]);
        });

        $last = [];
        foreach ($issued as $row) {
            if ($row->range_id === null || $row->number === null) {
                continue;
            }
            // Numbers look like PAT-WR-2026-00004 / CASE-WR-2026-00012 — the
            // year is embedded right before the counter, which is more
            // reliable than the record's own date for the year the counter
            // was actually drawn against.
            if (! preg_match('/-(\d{4})-(\d+)$/', $row->number, $m)) {
                continue;
            }
            $key = $m[1].'|'.$row->range_id;
            $last[$key] = max($last[$key] ?? 0, (int) $m[2]);
        }

        foreach ($last as $key => $number) {
            [$year, $rangeId] = explode('|', $key, 2);
            DB::table($table)->insert([
                $yearColumn => (int) $year,
                $rangeColumn => $rangeId,
                $numberColumn => $number,
            ]);
        }
    }
};
