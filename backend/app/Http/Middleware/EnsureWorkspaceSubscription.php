<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Services\Billing\WorkspaceSubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceSubscription
{
    public function __construct(
        private readonly WorkspaceSubscriptionService $subscriptions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->attributes->get('workspace');

        if (! $workspace instanceof Workspace) {
            abort(400, 'Workspace context required.');
        }

        if ($this->subscriptions->activeForWorkspace($workspace) === null) {
            abort(402, 'An active subscription is required for this workspace.');
        }

        return $next($request);
    }
}
