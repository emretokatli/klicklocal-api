<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Business\BusinessProfileService;
use App\Services\Workspace\OnboardingService;
use App\Services\Workspace\WorkspaceService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserOnboardingService
{
    public function __construct(
        private readonly WorkspaceService $workspaceService,
        private readonly BusinessProfileService $businessProfiles,
        private readonly OnboardingService $workspaceOnboarding,
    ) {}

    /**
     * @return array{user: User, token: string, resumed: bool}
     */
    public function registerWithEmail(string $email): array
    {
        $existing = User::where('email', $email)->first();

        if ($existing !== null && $existing->hasCompletedOnboarding()) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered. Please sign in instead.'],
            ]);
        }

        if ($existing !== null) {
            $existing->tokens()->delete();
            $token = $existing->createToken('auth-token')->plainTextToken;

            return [
                'user' => $existing->fresh(),
                'token' => $token,
                'resumed' => true,
            ];
        }

        $user = User::create([
            'name' => (string) str($email)->before('@'),
            'email' => $email,
            'password' => null,
            'onboarding_step' => 'get-started',
            'onboarding_data' => [],
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'resumed' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{user: User}
     */
    public function saveProgress(User $user, string $step, array $data = []): array
    {
        if ($user->hasCompletedOnboarding()) {
            throw ValidationException::withMessages([
                'onboarding' => ['Onboarding is already complete.'],
            ]);
        }

        $merged = array_merge($user->onboarding_data ?? [], $data);

        if (isset($merged['firstName']) && filled($merged['firstName'])) {
            $user->name = (string) $merged['firstName'];
        }

        $user->onboarding_step = $step;
        $user->onboarding_data = $merged;
        $user->save();

        return ['user' => $user->fresh()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{user: User, workspace: Workspace}
     */
    public function complete(User $user, array $data): array
    {
        if ($user->hasCompletedOnboarding()) {
            throw ValidationException::withMessages([
                'onboarding' => ['Onboarding is already complete.'],
            ]);
        }

        return DB::transaction(function () use ($user, $data) {
            $user->name = $data['first_name'];
            $user->password = Hash::make($data['password']);
            $user->onboarding_data = array_merge($user->onboarding_data ?? [], Arr::except($data, ['password', 'password_confirmation']));
            $user->onboarding_step = 'completed';
            $user->onboarding_completed_at = now();
            $user->save();

            $workspace = $this->workspaceService->create($user, [
                'name' => $data['business_name'],
            ]);

            $this->businessProfiles->upsert($user, $workspace->id, [
                'business_name' => $data['business_name'],
                'business_type' => $data['industry'],
                'city' => $data['city'] ?? null,
                'description' => $data['description'] ?? null,
                'products_services' => $data['description'] ?? null,
                'website' => $data['website'] ?? null,
                'team_size' => $data['team_size'] ?? null,
                'monthly_revenue' => $data['monthly_revenue'] ?? null,
                'customer_source' => $data['customer_source'] ?? null,
                'social_media_channels' => $data['social_media_channels'] ?? null,
                'target_audience' => $data['target_audience'] ?? null,
                'unique_value_proposition' => $data['unique_value_proposition'] ?? null,
                'additional_notes' => $data['additional_notes'] ?? null,
                'primary_goal' => $data['primary_goal'] ?? null,
            ]);

            $this->workspaceOnboarding->update($user, $workspace->id, [
                'completed' => true,
            ]);

            return [
                'user' => $user->fresh(),
                'workspace' => $workspace->fresh(),
            ];
        });
    }

    /**
     * @return array{user: User}
     */
    public function status(User $user): array
    {
        return ['user' => $user];
    }
}
