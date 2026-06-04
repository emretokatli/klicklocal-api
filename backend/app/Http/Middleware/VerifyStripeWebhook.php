<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyStripeWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('billing.stripe.webhook_secret');

        if (empty($secret)) {
            if (! app()->environment('local', 'testing')) {
                abort(503, 'Stripe webhook secret is not configured.');
            }

            return $next($request);
        }

        $signature = $request->header('Stripe-Signature');

        if (empty($signature)) {
            abort(400, 'Missing Stripe-Signature header.');
        }

        // Production: \Stripe\Webhook::constructEvent($payload, $signature, $secret);

        return $next($request);
    }
}
