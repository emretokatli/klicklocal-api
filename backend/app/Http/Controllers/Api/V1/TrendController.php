<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Workspace;
use App\Services\Trends\Factory\TrendProviderFactory;
use App\Services\Trends\TrendIndustryMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrendController extends Controller
{
    public function __construct(
        private readonly TrendProviderFactory $providers,
        private readonly TrendIndustryMatcher $matcher,
    ) {}

    /**
     * Trends for the current workspace, each annotated with an industry-fit flag,
     * a short AI comment and a suggested content format. No subscription needed.
     *
     * Trends are read exclusively through the TrendProviderInterface — the
     * controller never queries the trend tables directly.
     */
    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        if (! $workspace instanceof Workspace) {
            return ApiResponse::error('Workspace context required.', 400);
        }

        $provider = $this->providers->make();
        $topics = $provider->topics(limit: 20);

        $businessType = $workspace->businessProfile?->business_type;
        $matched = $this->matcher->match($businessType, $topics);

        return ApiResponse::success([
            'business_type' => $businessType,
            'trends' => array_map(static fn ($trend) => $trend->toArray(), $matched),
        ], 'Trends loaded.');
    }
}
