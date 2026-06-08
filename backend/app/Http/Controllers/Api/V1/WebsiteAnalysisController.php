<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\AnalyzeWebsiteRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Ai\WebsiteAnalysisService;
use Illuminate\Http\JsonResponse;

class WebsiteAnalysisController extends Controller
{
    public function __construct(
        private readonly WebsiteAnalysisService $analyzer,
    ) {}

    public function analyze(AnalyzeWebsiteRequest $request): JsonResponse
    {
        $analysis = $this->analyzer->analyze($request->validated());

        return ApiResponse::success(
            ['analysis' => $analysis->toArray()],
            'Website analyzed successfully.',
        );
    }
}
