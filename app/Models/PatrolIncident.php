<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A lightweight incident log entry — quicker to file than a full
 * {@see PatrolCaseReports} case, and optionally escalated into one from the
 * app afterwards.
 */
#[Fillable(['pi_id', 'pi_entry_id', 'pi_reported_by', 'pi_name', 'pi_details', 'pi_latitude', 'pi_longitude', 'pi_address', 'pi_reported_at', 'pi_created_at', 'pi_updated_at'])]
class PatrolIncident extends Model
{
    use HasFactory;

    protected $table = 'patrol_incidents';

    protected $primaryKey = 'pi_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'pi_created_at';

    public const UPDATED_AT = 'pi_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $incident): void {
            $incident->pi_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'pi_reported_at' => 'datetime',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PatrollingEntries::class, 'pi_entry_id', 'pe_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pi_reported_by', 'u_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(PatrolIncidentMedia::class, 'pim_incident_id', 'pi_id');
    }
}
