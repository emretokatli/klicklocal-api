<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Workspace;
use App\Services\Planning\ContentPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentPlanController extends Controller
{
    public function __construct(
        private readonly ContentPlanService $plans,
    ) {}

    /**
     * A weekly suggested content plan (day + category + platform + idea) for the
     * current workspace. Subscription-gated — this is the planning layer that
     * unlocks the full experience.
     */
    public function weekly(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        if (! $workspace instanceof Workspace) {
            return ApiResponse::error('Workspace context required.', 400);
        }

        $plan = $this->plans->weeklyPlan($workspace);

        return ApiResponse::success([
            'week_start' => $plan['week_start'],
            'suggestions' => array_map(
                static fn ($suggestion) => $suggestion->toArray(),
                $plan['suggestions'],
            ),
        ], 'Weekly content plan loaded.');
    }
}
