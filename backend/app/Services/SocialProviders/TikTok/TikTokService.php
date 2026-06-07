<?php

namespace App\Services\SocialProviders\TikTok;

use App\Enums\SocialAccountStatus;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SocialProviders\TikTok\DTOs\TikTokAccountDTO;
use App\Services\SocialProviders\TikTok\DTOs\TikTokTokenDTO;
use App\Services\SocialProviders\TikTok\DTOs\TikTokUserDTO;
use App\Services\SocialProviders\TikTok\Exceptions\TikTokOAuthException;
use Illuminate\Support\Facades\Log;

class TikTokService
{
    public function __construct(
        private readonly TikTokOAuthService $oauth,
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

            Log::info('TikTok account connected', [
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
        $account = $this->findTikTokAccount($workspace);

        if ($account === null) {
            return;
        }

        $account->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'status' => SocialAccountStatus::Disconnected,
        ]);

        Log::info('TikTok account disconnected', [
            'workspace_id' => $workspace->id,
            'social_account_id' => $account->id,
        ]);
    }

    public function refreshToken(SocialAccount $account): SocialAccount
    {
        if (! filled($account->refresh_token)) {
            throw TikTokOAuthException::tokenExchangeFailed('No refresh token available.');
        }

        $token = $this->oauth->refreshAccessToken($account->refresh_token);

        $account->update([
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken ?? $account->refresh_token,
            'token_expires_at' => $token->expiresIn !== null
                ? now()->addSeconds($token->expiresIn)
                : null,
            'status' => SocialAccountStatus::Connected,
        ]);

        Log::info('TikTok token refreshed', ['social_account_id' => $account->id]);

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

    public function status(Workspace $workspace): ?TikTokAccountDTO
    {
        $account = $this->findTikTokAccount($workspace);

        if ($account === null) {
            return null;
        }

        if ($account->isTokenExpired()) {
            $account->update(['status' => SocialAccountStatus::Expired]);
            $account->refresh();
        }

        return TikTokAccountDTO::fromModel($account);
    }

    public function findTikTokAccount(Workspace $workspace): ?SocialAccount
    {
        return SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'tiktok')
            ->first();
    }

    private function storeAccount(
        int $workspaceId,
        TikTokTokenDTO $token,
        TikTokUserDTO $profile,
    ): SocialAccount {
        return SocialAccount::query()->updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'provider' => 'tiktok',
                'provider_account_id' => $profile->openId ?: $token->openId,
            ],
            [
                'account_name' => $profile->displayName,
                'username' => $profile->displayName,
                'access_token' => $token->accessToken,
                'refresh_token' => $token->refreshToken,
                'token_expires_at' => $token->expiresIn !== null
                    ? now()->addSeconds($token->expiresIn)
                    : null,
                'status' => SocialAccountStatus::Connected,
                'metadata' => [
                    'scopes' => $token->scopes,
                    'union_id' => $profile->unionId,
                    'avatar_url' => $profile->avatarUrl,
                    'refresh_expires_at' => $token->refreshExpiresIn !== null
                        ? now()->addSeconds($token->refreshExpiresIn)->toIso8601String()
                        : null,
                    'connected_at' => now()->toIso8601String(),
                ],
            ],
        );
    }
}
