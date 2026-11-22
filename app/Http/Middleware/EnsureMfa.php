<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->requiresMfa() || ! $user->isActive()) {
            return $next($request);
        }

        if ($request->session()->boolean('security.mfa_verified')) {
            return $next($request);
        }

        // Authentication and enrollment endpoints must remain accessible
        // while all other routes, including public document access with auth,
        // are blocked until the second factor is satisfied.
        if ($request->routeIs('mfa.*', 'logout')) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Verifikasi dua faktor diperlukan.'], 403)
            : redirect()->route('mfa.challenge');
    }
}
