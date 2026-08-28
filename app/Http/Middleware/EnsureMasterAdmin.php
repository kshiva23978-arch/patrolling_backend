<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a section behind `Admin::isMasterAdmin()` regardless of what the
 * authenticated admin's role's `ro_permissions` says — unlike
 * `EnsureAdminPermission`, this can't be granted to a Department Admin or
 * Ranger role by ticking a checkbox in the Roles editor. Reserved for
 * sections where granting access below Master Admin would itself be a
 * privilege-escalation risk (Roles, Designations) — see the routes this is
 * attached to for the current list. Runs after `EnsureAdmin`, which already
 * guarantees `$request->user()` is an `Admin`.
 */
class EnsureMasterAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->isMasterAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'This section is only accessible to a Master Admin (System Administrator).',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
