<?php

namespace App\Models;

use App\Support\RangeNumberSequence;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Patrol-id master (patrol ids): one row per (year, range) holding
 * the last number issued to that range that year — each range counts on
 * its own.
 *
 * Read-only convenience for reporting/inspection. The table's primary key
 * is composite (year, range), which Eloquent can't model, so numbers are
 * never issued through this class — {@see RangeNumberSequence::next()}
 * does that with the query builder and a row lock.
 */
#[Fillable(['pns_year', 'pns_range_id', 'pns_last_number'])]
class PatrolNumberSequence extends Model
{
    protected $table = 'patrol_number_sequences';

    public $incrementing = false;

    public $timestamps = false;
}
