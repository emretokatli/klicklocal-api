<?php

namespace App\Services\SocialProviders\Instagram;

use App\Enums\SocialAccountStatus;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SocialProviders\Instagram\DTOs\InstagramAccountDTO;
use App\Services\SocialProviders\Instagram\DTOs\InstagramTokenDTO;
use App\Services\SocialProviders\Instagram\Exceptions\InstagramOAuthException;
use Illuminate\Support\Facades\Log;

class InstagramService
{
    public function __construct(
        private readonly InstagramOAuthService $oauth,
    ) {}

    public function connectUrl(Workspace $workspace, User $user): string
    {
        $state = $this->oauth->createState($workspace, $user);

        return $this->oauth->buildAuthorizationUrl($state);
    }

    public function handleCallback(string $code, string $state): SocialAccount
    {
        $oauthState = $this->oauth->resolveState($state);

        try {
            $token = $this->oauth->exchangeAuthorizationCode($code);
            $profile = $this->oauth->fetchUserProfile($token->accessToken);
            $account = $this->storeAccount($oauthState->workspace_id, $token, $profile);

            Log::info('Instagram account connected', [
                'workspace_id' => $oauthState->workspace_id,
                'social_account_id' => $account->id,
                'username' => $account->username,
            ]);

            return $account;
        } finally {
            $this->oauth->consumeState($oauthState);
        }
    }

    public function disconnect(Workspace $workspace): void
    {
        $account = $this->findInstagramAccount($workspace);

        if ($account === null) {
            return;
        }

        $account->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'status' => SocialAccountStatus::Disconnected,
        ]);

        Log::info('Instagram account disconnected', [
            'workspace_id' => $workspace->id,
            'social_account_id' => $account->id,
        ]);
    }

    public function refreshToken(SocialAccount $account): SocialAccount
    {
        if (! filled($account->access_token)) {
            throw InstagramOAuthException::tokenExchangeFailed('No access token to refresh.');
        }

        $token = $this->oauth->refreshLongLivedToken($account->access_token);

        $account->update([
            'access_token' => $token->accessToken,
            'token_expires_at' => $token->expiresIn !== null
                ? now()->addSeconds($token->expiresIn)
                : null,
            'status' => SocialAccountStatus::Connected,
        ]);

        Log::info('Instagram token refreshed', ['social_account_id' => $account->id]);

        return $account->fresh();
    }

    public function validateToken(SocialAccount $account): bool
    {
        if (! filled($account->access_token)) {
            return false;
        }

        $valid = $this->oauth->validateAccessToken($account->access_token);

        if (! $valid) {
            $account->update(['status' => SocialAccountStatus::Expired]);
        }

        return $valid;
    }

    public function status(Workspace $workspace): ?InstagramAccountDTO
    {
        $account = $this->findInstagramAccount($workspace);

        if ($account === null) {
            return null;
        }

        if ($account->isTokenExpired()) {
            $account->update(['status' => SocialAccountStatus::Expired]);
            $account->refresh();
        }

        return InstagramAccountDTO::fromModel($account);
    }

    public function findInstagramAccount(Workspace $workspace): ?SocialAccount
    {
        return SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'instagram')
            ->first();
    }

    private function storeAccount(
        int $workspaceId,
        InstagramTokenDTO $token,
        \App\Services\SocialProviders\Instagram\DTOs\InstagramUserDTO $profile,
    ): SocialAccount {
        return SocialAccount::query()->updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'provider' => 'instagram',
                'provider_account_id' => $profile->id ?: $token->userId,
            ],
            [
                'account_name' => $profile->name,
                'username' => $profile->username,
                'access_token' => $token->accessToken,
                'refresh_token' => null,
                'token_expires_at' => $token->expiresIn !== null
                    ? now()->addSeconds($token->expiresIn)
                    : null,
                'status' => SocialAccountStatus::Connected,
                'metadata' => [
                    'permissions' => $token->permissions,
                    'account_type' => $profile->accountType,
                    'instagram_user_id' => $token->userId,
                    'connected_at' => now()->toIso8601String(),
                ],
            ],
        );
    }
}
