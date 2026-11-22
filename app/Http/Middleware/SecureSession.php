<?php

namespace App\Http\Middleware;

use App\Support\SessionState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SecureSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $session = $request->session();
            if (Auth::guard('web')->viaRemember() && ! $session->has('security.user_id') && $user->isActive() && $user->remember_expires_at?->isFuture()) {
                SessionState::establish($request, $user);
                $session->forget(['auth.password_confirmed_at', 'security.mfa_verified']);
            }
            $now = now()->timestamp;
            $valid = $user->isActive()
                && $session->get('security.user_id') === $user->id
                && $session->get('security.version') === (int) $user->auth_version
                && $now - (int) $session->get('security.last_activity') < config('session.lifetime') * 60
                && $now - (int) $session->get('security.started_at') < config('repository.session_absolute_minutes') * 60;

            if (! $valid) {
                Log::notice('auth.session_revoked', ['user_id' => $user->id]);
                Auth::guard('web')->logoutCurrentDevice();
                $session->invalidate();
                $session->regenerateToken();

                return $request->expectsJson()
                    ? response()->json(['message' => 'Sesi berakhir. Silakan masuk kembali.'], 401)
                    : redirect()->route('login')->with('status', 'Sesi berakhir. Silakan masuk kembali.');
            }
            if (! $request->routeIs('admin.works.status')) {
                $session->put('security.last_activity', $now);
            }
        }

        return $next($request);
    }
}
