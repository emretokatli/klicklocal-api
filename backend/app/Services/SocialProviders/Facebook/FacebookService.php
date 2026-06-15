<?php

namespace App\Services\SocialProviders\Facebook;

use App\Enums\SocialAccountStatus;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SocialProviders\Facebook\DTOs\FacebookAccountDTO;
use App\Services\SocialProviders\Facebook\DTOs\FacebookPageDTO;
use App\Services\SocialProviders\Facebook\DTOs\FacebookTokenDTO;
use App\Services\SocialProviders\Facebook\DTOs\FacebookUserDTO;
use App\Services\SocialProviders\Facebook\Exceptions\FacebookOAuthException;
use Illuminate\Support\Facades\Log;

class FacebookService
{
    public function __construct(
        private readonly FacebookOAuthService $oauth,
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
            $shortLived = $this->oauth->exchangeAuthorizationCode($code);
            $longLived = $this->oauth->exchangeLongLivedToken($shortLived);
            $profile = $this->oauth->fetchUserProfile($longLived->accessToken);

            // A Page access token derived from a long-lived user token does not
            // expire, so it is the credential we publish with.
            $pages = $this->oauth->fetchPages($longLived->accessToken);
            $page = $pages[0];

            $account = $this->storeAccount(
                $oauthState->workspace_id,
                $longLived,
                $profile,
                $page,
                $pages,
            );

            Log::info('Facebook account connected', [
                'workspace_id' => $oauthState->workspace_id,
                'social_account_id' => $account->id,
                'page_id' => $page->id,
                'page_name' => $page->name,
            ]);

            return $account;
        } finally {
            $this->oauth->consumeState($oauthState);
        }
    }

    public function disconnect(Workspace $workspace): void
    {
        $account = $this->findFacebookAccount($workspace);

        if ($account === null) {
            return;
        }

        $account->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'status' => SocialAccountStatus::Disconnected,
        ]);

        Log::info('Facebook account disconnected', [
            'workspace_id' => $workspace->id,
            'social_account_id' => $account->id,
        ]);
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

    public function status(Workspace $workspace): ?FacebookAccountDTO
    {
        $account = $this->findFacebookAccount($workspace);

        if ($account === null) {
            return null;
        }

        if ($account->isTokenExpired()) {
            $account->update(['status' => SocialAccountStatus::Expired]);
            $account->refresh();
        }

        return FacebookAccountDTO::fromModel($account);
    }

    public function findFacebookAccount(Workspace $workspace): ?SocialAccount
    {
        return SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'facebook')
            ->first();
    }

    /**
     * @param  list<FacebookPageDTO>  $allPages
     */
    private function storeAccount(
        int $workspaceId,
        FacebookTokenDTO $userToken,
        FacebookUserDTO $profile,
        FacebookPageDTO $page,
        array $allPages,
    ): SocialAccount {
        return SocialAccount::query()->updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'provider' => 'facebook',
                'provider_account_id' => $page->id,
            ],
            [
                'account_name' => $page->name,
                'username' => $page->name,
                // access_token = the Page access token used for publishing.
                'access_token' => $page->accessToken,
                'refresh_token' => null,
                'token_expires_at' => $userToken->expiresIn !== null
                    ? now()->addSeconds($userToken->expiresIn)
                    : null,
                'status' => SocialAccountStatus::Connected,
                'metadata' => [
                    'page_id' => $page->id,
                    'page_name' => $page->name,
                    'page_category' => $page->category,
                    'page_tasks' => $page->tasks,
                    'user_id' => $profile->id,
                    'user_name' => $profile->name,
                    'user_access_token' => $userToken->accessToken,
                    'scopes' => config('facebook.scopes', []),
                    'available_pages' => array_map(
                        static fn (FacebookPageDTO $p): array => ['id' => $p->id, 'name' => $p->name],
                        $allPages,
                    ),
                    'connected_at' => now()->toIso8601String(),
                ],
            ],
        );
    }
}
