<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FreshPasswordConfirmation
{
    public function handle(Request $request, Closure $next): Response
    {
        $confirmed = (int) $request->session()->get('auth.password_confirmed_at', 0);
        $limit = max(1, (int) config('repository.fresh_password_minutes', 5)) * 60;
        if ($confirmed <= 0 || time() - $confirmed >= $limit) {
            $request->session()->forget('auth.password_confirmed_at');

            return $request->expectsJson()
                ? response()->json(['message' => 'Konfirmasi password diperlukan.'], 403)
                : redirect()->guest(route('password.confirm'));
        }

        return $next($request);
    }
}
