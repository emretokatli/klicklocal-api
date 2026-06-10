<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyRevenueCatWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.revenuecat.webhook_auth_token');

        if (empty($token)) {
            if (! app()->environment('local', 'testing')) {
                abort(503, 'RevenueCat webhook auth token is not configured.');
            }

            return $next($request);
        }

        $authorization = (string) $request->header('Authorization', '');

        // RevenueCat sends the configured Authorization header value verbatim;
        // accept it with or without a "Bearer " prefix.
        if (! hash_equals($token, $authorization)
            && ! hash_equals('Bearer '.$token, $authorization)) {
            abort(401, 'Invalid RevenueCat webhook authorization.');
        }

        return $next($request);
    }
}
