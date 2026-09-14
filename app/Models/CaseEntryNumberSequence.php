<?php

namespace App\Models;

use App\Support\RangeNumberSequence;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Case-number master (Case module case and filing numbers): one row per (year, range) holding
 * the last number issued to that range that year — each range counts on
 * its own.
 *
 * Read-only convenience for reporting/inspection. The table's primary key
 * is composite (year, range), which Eloquent can't model, so numbers are
 * never issued through this class — {@see RangeNumberSequence::next()}
 * does that with the query builder and a row lock.
 */
#[Fillable(['cens_year', 'cens_range_id', 'cens_last_number'])]
class CaseEntryNumberSequence extends Model
{
    protected $table = 'case_entry_number_sequences';

    public $incrementing = false;

    public $timestamps = false;
}
