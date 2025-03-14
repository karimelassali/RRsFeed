<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
<<<<<<< HEAD
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class, // معطل لـ API Stateless
=======
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
>>>>>>> publishing
        ]);
        $middleware->validateCsrfTokens(except: ['api/*']);

        $middleware->alias([
            'auth.api' => \App\Http\Middleware\AuthenticateApi::class,
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
<<<<<<< HEAD
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            return '/login';
=======
            \Log::info('RedirectGuestsTo triggered (fallback)', [
                'path' => $request->path(),
                'expectsJson' => $request->expectsJson(),
            ]);
            return response()->json(['message' => 'Unauthenticated'], 401);
>>>>>>> publishing
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();