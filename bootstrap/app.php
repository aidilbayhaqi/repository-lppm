<?php

use App\Http\Middleware\EnsureActive;
use App\Http\Middleware\EnsureMfa;
use App\Http\Middleware\FreshPasswordConfirmation;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecureSession;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [SecureSession::class, EnsureMfa::class]);
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);
        $middleware->trustHosts(at: fn () => ['^'.preg_quote(parse_url(config('app.url'), PHP_URL_HOST), '/').'$'], subdomains: false);
        $middleware->alias([
            'active' => EnsureActive::class,
            'fresh-password' => FreshPasswordConfirmation::class,
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation']);
        $exceptions->render(function (UniqueConstraintViolationException $exception, Request $request) {
            $errors = ['conflict' => 'Data yang sama baru saja disimpan. Muat ulang dan periksa nilai yang harus unik.'];

            return $request->expectsJson()
                ? response()->json(['message' => $errors['conflict'], 'errors' => $errors], 409)
                : back()->withErrors($errors)->withInput($request->except(['password', 'password_confirmation', 'current_password']));
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
