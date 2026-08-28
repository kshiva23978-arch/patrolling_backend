<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared row-level range scoping for admin-panel controllers — a
 * `department_admin`/`ranger`-level admin (see `Admin::accessibleRangeIds`)
 * only ever sees rows belonging to their assigned range(s); a
 * `master_admin`-level (or role-less) admin is unrestricted. This is
 * deliberately separate from `EnsureAdminPermission`/`ro_permissions`,
 * which gates *sections* (can this admin see "beats" at all) rather than
 * *rows* (which beats, within a section they can see).
 */
trait ScopesToRanges
{
    /** Range ids [$request]'s admin is scoped to, or `null` if unrestricted. */
    protected function accessibleRangeIds(Request $request): ?array
    {
        $user = $request->user();

        return $user instanceof Admin ? $user->accessibleRangeIds() : null;
    }

    /** `true` if [$request]'s admin is unrestricted (sees every range). */
    protected function isUnrestrictedAdmin(Request $request): bool
    {
        return $this->accessibleRangeIds($request) === null;
    }

    /**
     * Aborts with a 403 unless [$request]'s admin can access [$rangeId] —
     * use before creating/attaching a row under a caller-supplied range id.
     */
    protected function assertRangeAccessible(Request $request, ?string $rangeId): void
    {
        if ($rangeId === null) {
            return;
        }

        $ids = $this->accessibleRangeIds($request);

        if ($ids !== null && ! in_array($rangeId, $ids, true)) {
            abort(403, 'You do not have access to this range.');
        }
    }

    /** Filters [$query] to [$request]'s admin's accessible ranges via [$column], when scoped. */
    protected function scopeToAccessibleRanges(Builder $query, Request $request, string $column): Builder
    {
        $ids = $this->accessibleRangeIds($request);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($column, $ids);
    }
}
