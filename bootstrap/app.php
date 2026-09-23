<?php

use App\Http\Middleware\EnsureMustChangePassword;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsBuyer;
use App\Http\Middleware\EnsureUserIsVendor;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureMustChangePassword::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'buyer' => EnsureUserIsBuyer::class,
            'vendor' => EnsureUserIsVendor::class,
        ]);

        $middleware->redirectGuestsTo('/login');

        // Stripe's server has no session and sends no CSRF token -- the
        // webhook route's own signature verification (CLAUDE.md §14 stripe
        // integration, StripeWebhookController) is what proves the request
        // is genuine instead.
        $middleware->validateCsrfTokens(except: [
            'webhooks/stripe',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
