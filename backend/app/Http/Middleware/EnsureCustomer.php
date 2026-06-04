<?php

namespace App\Http\Middleware;

use App\Services\Authorization\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer routes: authenticated users who are not platform-only admins.
 * Platform staff may also use customer routes when not acting as admin.
 */
class EnsureCustomer
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        return $next($request);
    }
}
