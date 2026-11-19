<?php

namespace App\Providers;

use App\Models\Buku;
use App\Models\KekayaanIntelektual;
use App\Models\User;
use App\Policies\WorkPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('mutations', function (Request $request) {
            return $request->isMethodSafe()
                ? Limit::none()
                : Limit::perMinute(60)->by((string) ($request->user()?->id ?? $request->ip()));
        });
        TrustProxies::at(config('repository.trusted_proxies'));
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
                Limit::perMinute(10)->by('login-account:'.hash('sha256', mb_strtolower(trim((string) $request->input('email'))))),
            ];
        });
        RateLimiter::for('auth-requests', fn (Request $request) => [
            Limit::perMinute(5)->by('auth-ip:'.$request->ip()),
            Limit::perHour(10)->by('auth-account:'.hash('sha256', mb_strtolower(trim((string) $request->input('email'))))),
        ]);
        Gate::policy(Buku::class, WorkPolicy::class);
        Gate::policy(KekayaanIntelektual::class, WorkPolicy::class);
        Gate::define('manage-reference', fn (User $user) => $user->isActive() && $user->isStaff());
        Gate::define('manage-users', fn (User $user) => $user->isActive() && $user->isAdmin());
    }
}
