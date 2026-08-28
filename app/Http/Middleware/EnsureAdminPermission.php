<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates one admin-panel section behind the authenticated admin's role
 * permissions (see `Roles::hasAdminPermission`) — runs after `EnsureAdmin`,
 * which already guarantees `$request->user()` is an `Admin`.
 *
 * A read request (`GET`/`HEAD`) needs `view`; anything else (`POST`,
 * `PATCH`/`PUT`, `DELETE`) needs `manage` — so one middleware entry per
 * route group covers both, rather than needing to split every resource's
 * routes by verb. Usage: `->middleware('admin.permission:patrollings')`.
 */
class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next, string $section): Response
    {
        $level = $request->isMethod('get') || $request->isMethod('head') ? 'view' : 'manage';

        if (! $request->user()->hasAdminPermission($section, $level)) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to ".($level === 'view' ? 'view' : 'manage')." this section.",
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
