<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();
        $requestId = (string) Str::uuid();
        $start = hrtime(true);
        $response = $next($request);
        $nonce = Vite::cspNonce();
        $devConnect = app()->environment('local') ? ' http://localhost:5173 http://127.0.0.1:5173 ws://localhost:5173 ws://127.0.0.1:5173' : '';
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'{$devConnect}; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-Request-ID', $requestId);
        if (str_contains($response->headers->get('Content-Type', ''), 'text/html') || $request->user()) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        if (app()->environment('production') && ! $request->is('up', 'health')) {
            Log::info('http.request', [
                'request_id' => $requestId, 'user_id' => $request->user()?->id,
                'route' => $request->route()?->getName(), 'method' => $request->method(),
                'status' => $response->getStatusCode(), 'duration_ms' => round((hrtime(true) - $start) / 1_000_000, 2),
            ]);
        }

        return $response;
    }
}
