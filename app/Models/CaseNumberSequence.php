<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Case-number master: one row per calendar year holding the last case
 * number issued that year. {@see PatrolEntryController::generateCaseNumber()}
 * locks the row and increments it atomically so concurrent submissions
 * never collide.
 */
#[Fillable(['cns_year', 'cns_last_number'])]
class CaseNumberSequence extends Model
{
    protected $table = 'case_number_sequences';

    protected $primaryKey = 'cns_year';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;
}
