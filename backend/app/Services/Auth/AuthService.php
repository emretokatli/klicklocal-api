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
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
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
        ];
    }
}
