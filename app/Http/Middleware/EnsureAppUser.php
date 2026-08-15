<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppUser
{
    /**
     * Allow only requests from an authenticated non-admin user (Flutter field app).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin() !== false) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is only accessible to app users.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
