<?php

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Which physical phone a request comes from — the app sends a stable,
 * randomly generated id in an `X-Device-Id` header on every call (see the
 * app's `SessionStorage.deviceId`). It survives logout/re-login, unlike the
 * Sanctum token, which is minted fresh on each login.
 *
 * Used to keep *unfinished* patrols and cases private to the device that
 * created them: the same ranger login on two phones must not see (or be
 * able to touch) each other's in-progress work until the creating phone
 * has uploaded it — see {@see scopeVisibleFrom}. Once completed, an entry
 * is visible per the ordinary leader/range rules.
 */
final class DeviceIdentity
{
    public const HEADER = 'X-Device-Id';

    public static function fromRequest(Request $request): ?string
    {
        $raw = trim((string) $request->header(self::HEADER, ''));
        if ($raw === '' || strlen($raw) > 64 || ! preg_match('/^[A-Za-z0-9._:-]+$/', $raw)) {
            return null;
        }

        return $raw;
    }

    /**
     * Whether the calling device may see [$entry] given its creation
     * stamps. Completed entries are always visible (subject to the caller's
     * own leader/range check). An unfinished one is visible only from the
     * device that created it — matched by device id, or, for rows created
     * before device ids existed, by the login token that created it.
     */
    public static function mayViewUnfinished(Request $request, bool $isCompleted, ?string $createdDeviceId, ?int $createdTokenId): bool
    {
        if ($isCompleted) {
            return true;
        }

        if ($createdDeviceId !== null) {
            return $createdDeviceId === self::fromRequest($request);
        }

        $tokenId = $request->user()?->currentAccessToken()?->id;

        return $createdTokenId !== null && $tokenId !== null && $createdTokenId === $tokenId;
    }

    /**
     * Query-side twin of {@see mayViewUnfinished}: restricts a list to
     * completed entries plus this device's own unfinished ones.
     *
     * @param  Builder  $query  the entry model's query
     * @param  string  $statusColumn  e.g. `pe_status`
     * @param  string  $deviceColumn  e.g. `pe_created_device_id`
     * @param  string  $tokenColumn  e.g. `pe_created_via_token_id`
     */
    public static function scopeVisibleFrom(Request $request, Builder $query, string $statusColumn, string $completedStatus, string $deviceColumn, string $tokenColumn): Builder
    {
        $deviceId = self::fromRequest($request);
        $tokenId = $request->user()?->currentAccessToken()?->id;

        return $query->where(function (Builder $q) use ($statusColumn, $completedStatus, $deviceColumn, $tokenColumn, $deviceId, $tokenId) {
            $q->where($statusColumn, $completedStatus);
            if ($deviceId !== null) {
                $q->orWhere($deviceColumn, $deviceId);
            }
            if ($tokenId !== null) {
                $q->orWhere(fn (Builder $legacy) => $legacy->whereNull($deviceColumn)->where($tokenColumn, $tokenId));
            }
        });
    }
}
