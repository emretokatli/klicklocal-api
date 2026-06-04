<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Services\Billing\FeatureAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureQuota
{
    public function __construct(
        private readonly FeatureAccessService $features,
    ) {}

    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $workspace = $request->attributes->get('workspace');

        if (! $workspace instanceof Workspace) {
            abort(400, 'Workspace context required for feature quota check.');
        }

        $this->features->assertCanUseFeature($workspace, $featureKey);

        return $next($request);
    }
}
