<?php

namespace App\Http\Middleware;

use App\Services\Authorization\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $workspace = $request->attributes->get('workspace');

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        if ($workspace !== null) {
            $this->authorization->assertWorkspacePermission($user, $workspace, $permission);
        } else {
            $this->authorization->assertPlatformPermission($user, $permission);
        }

        return $next($request);
    }
}
