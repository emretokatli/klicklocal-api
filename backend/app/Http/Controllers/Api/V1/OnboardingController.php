<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\UpdateOnboardingRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Workspace\OnboardingService;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboarding,
    ) {}

    public function update(UpdateOnboardingRequest $request, int $workspace): JsonResponse
    {
        $updated = $this->onboarding->update(
            $request->user(),
            $workspace,
            $request->validated(),
        );

        return ApiResponse::success(
            ['workspace' => $updated],
            'Onboarding updated.',
        );
    }
}
