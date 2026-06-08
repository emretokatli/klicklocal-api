<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteOnboardingRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterEmailRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateUserOnboardingRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return ApiResponse::success([
            'user' => $result['user'],
            'token' => $result['token'],
        ], 'Registration successful.', 201);
    }

    public function registerEmail(RegisterEmailRequest $request): JsonResponse
    {
        $result = $this->authService->registerWithEmail($request->validated('email'));

        return ApiResponse::success([
            'user' => $result['user'],
            'token' => $result['token'],
            'resumed' => $result['resumed'],
        ], $result['resumed'] ? 'Onboarding resumed.' : 'Registration started.', 201);
    }

    public function onboardingStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'user' => $user,
            'onboarding_completed' => $user->hasCompletedOnboarding(),
            'onboarding_step' => $user->onboarding_step,
            'onboarding_data' => $user->onboarding_data ?? [],
        ]);
    }

    public function updateOnboarding(UpdateUserOnboardingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->authService->updateOnboardingProgress(
            $request->user(),
            $validated['step'],
            $validated['data'] ?? [],
        );

        return ApiResponse::success([
            'user' => $result['user'],
            'onboarding_step' => $result['user']->onboarding_step,
            'onboarding_data' => $result['user']->onboarding_data ?? [],
        ], 'Onboarding progress saved.');
    }

    public function completeOnboarding(CompleteOnboardingRequest $request): JsonResponse
    {
        $result = $this->authService->completeOnboarding(
            $request->user(),
            $request->validated(),
        );

        return ApiResponse::success([
            'user' => $result['user'],
            'workspace' => $result['workspace'],
        ], 'Onboarding completed.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return ApiResponse::success([
            'user' => $result['user'],
            'token' => $result['token'],
            'onboarding_completed' => $result['user']->hasCompletedOnboarding(),
        ], 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        $workspaceId = $request->query('workspace_id');
        $payload = $this->authService->me(
            $request->user(),
            $workspaceId !== null ? (int) $workspaceId : null,
        );

        return ApiResponse::success($payload);
    }
}
