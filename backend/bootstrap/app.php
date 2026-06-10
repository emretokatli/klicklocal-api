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
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'platform.admin' => \App\Http\Middleware\EnsurePlatformAdmin::class,
            'customer' => \App\Http\Middleware\EnsureCustomer::class,
            'workspace.team' => \App\Http\Middleware\SetWorkspaceTeam::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'feature.quota' => \App\Http\Middleware\EnsureFeatureQuota::class,
            'workspace.subscription' => \App\Http\Middleware\EnsureWorkspaceSubscription::class,
            'subscription.required' => \App\Http\Middleware\EnsureWorkspaceSubscription::class,
            'stripe.webhook' => \App\Http\Middleware\VerifyStripeWebhook::class,
            'revenuecat.webhook' => \App\Http\Middleware\VerifyRevenueCatWebhook::class,
        ]);

        // API uses Bearer tokens — never redirect to a non-existent "login" web route.
        $middleware->redirectGuestsTo(function (Request $request): ?string {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return null;
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return \App\Http\Responses\ApiResponse::error(
                    'Validation failed.',
                    422,
                    $e->errors(),
                );
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return \App\Http\Responses\ApiResponse::error(
                    'Unauthenticated.',
                    401,
                );
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->is('api/*')) {
                return \App\Http\Responses\ApiResponse::error(
                    $e->getMessage() ?: 'Forbidden.',
                    403,
                );
            }
        });

        $exceptions->render(function (\Symfony\Component\Routing\Exception\RouteNotFoundException $e, $request) {
            if ($request->is('api/*')) {
                return \App\Http\Responses\ApiResponse::error(
                    'Unauthenticated.',
                    401,
                );
            }
        });
    })->create();
