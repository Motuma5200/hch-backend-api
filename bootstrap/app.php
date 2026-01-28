<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Optional: Customize cookie encryption (exclude certain cookies from being encrypted)
        // $middleware->encryptCookies(except: [
        //     'some_cookie_name',
        // ]);

        // If you need to fully customize/redefine the 'web' group (rarely needed),
        // you can do it like this — but usually you DON'T need this block:
        //
        // $middleware->group('web', [
        //     \Illuminate\Cookie\Middleware\EncryptCookies::class,
        //     \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        //     \Illuminate\Session\Middleware\StartSession::class,
        //     \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        //     \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        //     \Illuminate\Routing\Middleware\SubstituteBindings::class,
        // ]);

        // Your existing API customization
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Your existing alias
        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();