<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\BeachCleaningActivity;
use App\Models\CaseEntry;
use App\Models\PatrollingEntries;

/**
 * The cross-module "one active Patrol/Case/Activity drive at a time" rule
 * shared by PatrolEntryController::store(), CaseEntryController::store(),
 * and ActivityController::store(). Only an IN_PROGRESS (started, not yet
 * ended) item blocks — a created-but-not-started PENDING patrol/case never
 * does, since a ranger routinely creates one ahead of actually starting it
 * (an Activity has no PENDING state of its own: it's IN_PROGRESS the
 * instant it's created).
 *
 * Beach Cleaning is deliberately its own island, not part of this group —
 * see {@see hasInProgressBeachCleaning}: a ranger mid-patrol can still kick
 * off a cleaning drive (and vice versa) without either blocking the other.
 *
 * Scoped per device (Sanctum token, see AuthController::attemptLogin), not
 * per account: the same ranger is allowed one active item on each of
 * several devices, just not two at once from the same one.
 */
class UnfinishedWorkChecker
{
    public function hasInProgressWork(string $userId, ?int $tokenId, ?string $deviceId = null): bool
    {
        // Patrols/cases are stamped with the creating phone's device id
        // (see App\Support\DeviceIdentity), which survives re-login — so
        // "this device's own unfinished work" matches by device when the
        // app sends one, and only falls back to the login token otherwise
        // (older app builds, or rows created before device ids existed).
        $hasPatrol = PatrollingEntries::where('pe_patrol_leader_id', $userId)
            ->where(function ($q) use ($tokenId, $deviceId) {
                $q->whereRaw('1 = 0');
                if ($deviceId !== null) {
                    $q->orWhere('pe_created_device_id', $deviceId);
                }
                if ($tokenId !== null) {
                    $q->orWhere(fn ($legacy) => $legacy->whereNull('pe_created_device_id')->where('pe_created_via_token_id', $tokenId));
                }
                if ($deviceId === null && $tokenId === null) {
                    $q->orWhereRaw('1 = 1');
                }
            })
            ->where('pe_status', PatrollingEntries::STATUS_IN_PROGRESS)
            ->exists();
        if ($hasPatrol) {
            return true;
        }

        $hasCase = CaseEntry::where('ce_leader_id', $userId)
            ->where(function ($q) use ($tokenId, $deviceId) {
                $q->whereRaw('1 = 0');
                if ($deviceId !== null) {
                    $q->orWhere('ce_created_device_id', $deviceId);
                }
                if ($tokenId !== null) {
                    $q->orWhere(fn ($legacy) => $legacy->whereNull('ce_created_device_id')->where('ce_created_via_token_id', $tokenId));
                }
                if ($deviceId === null && $tokenId === null) {
                    $q->orWhereRaw('1 = 1');
                }
            })
            ->where('ce_status', CaseEntry::STATUS_IN_PROGRESS)
            ->exists();

        if ($hasCase) {
            return true;
        }

        return Activity::where('act_created_by', $userId)
            ->when($tokenId !== null, fn ($q) => $q->where('act_created_via_token_id', $tokenId))
            ->where('act_status', Activity::STATUS_IN_PROGRESS)
            ->exists();
    }

    /**
     * Beach Cleaning's own "one active drive at a time" rule — independent
     * of Patrol/Case/Activity (see this class's doc comment): a drive here
     * only ever blocks on *another* still-in-progress drive, never on an
     * unrelated module, and starting one never blocks those other modules
     * either.
     */
    public function hasInProgressBeachCleaning(string $userId, ?int $tokenId): bool
    {
        return BeachCleaningActivity::where('bca_created_by', $userId)
            ->when($tokenId !== null, fn ($q) => $q->where('bca_created_via_token_id', $tokenId))
            ->where('bca_status', BeachCleaningActivity::STATUS_IN_PROGRESS)
            ->exists();
    }
}
