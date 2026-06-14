<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessProfile\UpdateBusinessProfileRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Workspace;
use App\Services\Business\BusinessAnalysisService;
use App\Services\Business\BusinessProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessProfileController extends Controller
{
    public function __construct(
        private readonly BusinessProfileService $profiles,
        private readonly BusinessAnalysisService $analysis,
    ) {}

    public function show(Request $request, int $workspace): JsonResponse
    {
        $profile = $this->profiles->show($request->user(), $workspace);

        return ApiResponse::success(['business_profile' => $profile]);
    }

    public function update(UpdateBusinessProfileRequest $request, int $workspace): JsonResponse
    {
        $profile = $this->profiles->upsert(
            $request->user(),
            $workspace,
            $request->validated(),
        );

        return ApiResponse::success(
            ['business_profile' => $profile],
            'Business profile saved.',
        );
    }

    /**
     * Cached website analysis for the current workspace, tiered server-side:
     * teaser for unsubscribed workspaces, full for subscribed ones. No
     * subscription is required to reach this endpoint.
     */
    public function analysis(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        if (! $workspace instanceof Workspace) {
            return ApiResponse::error('Workspace context required.', 400);
        }

        $result = $this->analysis->forWorkspace(
            $workspace,
            refresh: $request->boolean('refresh'),
        );

        return ApiResponse::success($result, 'Website analysis loaded.');
    }
}
