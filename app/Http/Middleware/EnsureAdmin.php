<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Allow only requests from an authenticated admin user (Next.js admin panel).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof \App\Models\Admin) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is only accessible to admin users.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
