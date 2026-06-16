<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Analysis\SocialContentAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SocialContentAnalysisController extends Controller
{
    public function __construct(
        private readonly SocialContentAnalysisService $analysis,
    ) {}

    #[OA\Post(
        path: '/social-analysis/sync',
        summary: 'Fetch + normalize recent media/insights from connected accounts',
        security: [['sanctum' => []]],
        tags: ['Social Analysis'],
        responses: [new OA\Response(response: 200, description: 'Sync summary')],
    )]
    public function sync(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $result = $this->analysis->sync($workspace);

        return ApiResponse::success($result, 'Social content analysis synced.');
    }

    #[OA\Get(
        path: '/social-analysis',
        summary: 'List stored normalized content analyses',
        security: [['sanctum' => []]],
        tags: ['Social Analysis'],
        responses: [new OA\Response(response: 200, description: 'Analyses')],
    )]
    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $analyses = $this->analysis->list($workspace);

        return ApiResponse::success(['analyses' => $analyses]);
    }

    #[OA\Post(
        path: '/social-analysis/content-plan',
        summary: 'AI content-plan suggestion from analyzed data',
        security: [['sanctum' => []]],
        tags: ['Social Analysis'],
        responses: [new OA\Response(response: 200, description: 'Content plan suggestion')],
    )]
    public function contentPlan(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $suggestion = $this->analysis->suggestContentPlan($workspace);

        return ApiResponse::success(['content_plan' => $suggestion->toArray()]);
    }
}
