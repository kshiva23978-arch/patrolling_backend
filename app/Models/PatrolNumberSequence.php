<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Patrol-id master: one row per calendar year holding the last patrol
 * sequence number issued that year. {@see PatrolEntryController::generatePatrolId()}
 * locks the row and increments it atomically so concurrent submissions
 * never collide.
 */
#[Fillable(['pns_year', 'pns_last_number'])]
class PatrolNumberSequence extends Model
{
    protected $table = 'patrol_number_sequences';

    protected $primaryKey = 'pns_year';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;
}
