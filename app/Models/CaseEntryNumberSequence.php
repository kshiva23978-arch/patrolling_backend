<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Case-number master: one row per calendar year holding the last sequence
 * number issued that year. {@see CaseEntryController::generateCaseNumber()}
 * locks the row and increments it atomically so concurrent submissions
 * never collide. Separate from {@see PatrolNumberSequence}/{@see CaseNumberSequence}
 * since Case is its own entity.
 */
#[Fillable(['cens_year', 'cens_last_number'])]
class CaseEntryNumberSequence extends Model
{
    protected $table = 'case_entry_number_sequences';

    protected $primaryKey = 'cens_year';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;
}
