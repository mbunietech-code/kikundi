<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')->group(base_path('routes/integrations.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\SetLocale::class]);

        // Tunnels such as ngrok end HTTPS and forward from this machine. Trusting
        // only loopback keeps links on https without letting outside clients fake
        // their IP (the Beem IP allowlist relies on it).
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        // Members and officers each have their own sign-in page.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('mwanachama*') ? route('member.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('mwanachama*') ? route('member.home') : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('integrations/*') || $request->expectsJson(),
        );
    })->create();
