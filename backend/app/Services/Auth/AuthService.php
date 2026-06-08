<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Authorization\AuthorizationService;
use App\Services\Billing\BillingService;
use App\Services\Billing\FeatureAccessService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly SubscriptionService $subscriptions,
        private readonly FeatureAccessService $features,
        private readonly BillingService $billing,
        private readonly UserOnboardingService $userOnboarding,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'onboarding_completed_at' => now(),
            'onboarding_step' => 'completed',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * @return array{user: User, token: string, resumed: bool}
     */
    public function registerWithEmail(string $email): array
    {
        return $this->userOnboarding->registerWithEmail($email);
    }

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if ($user && $user->password === null) {
            throw ValidationException::withMessages([
                'email' => ['Please continue your registration using the same email address.'],
            ]);
        }

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->tokens()->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * @throws AuthenticationException
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token === null) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $token->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function me(User $user, ?int $workspaceId = null): array
    {
        $workspace = $workspaceId !== null
            ? Workspace::query()->find($workspaceId)
            : null;

        $billing = $workspace
            ? $this->billing->overview($workspace)
            : null;

        return [
            'user' => $user,
            'abilities' => $this->authorization->abilitiesForUser($user, $workspace),
            'is_platform_admin' => $this->authorization->isPlatformAdmin($user),
            'subscription_limits' => $workspace
                ? $this->subscriptions->limitsForWorkspace($workspace)
                : $this->subscriptions->limitsForUser($user),
            'billing' => $billing,
            'onboarding_completed' => $user->hasCompletedOnboarding(),
            'onboarding_step' => $user->onboarding_step,
            'onboarding_data' => $user->onboarding_data ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{user: User, workspace: Workspace}
     */
    public function completeOnboarding(User $user, array $data): array
    {
        return $this->userOnboarding->complete($user, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{user: User}
     */
    public function updateOnboardingProgress(User $user, string $step, array $data = []): array
    {
        return $this->userOnboarding->saveProgress($user, $step, $data);
    }
}
