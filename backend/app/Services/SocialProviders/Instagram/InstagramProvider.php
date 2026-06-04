<?php

namespace App\Services\SocialProviders\Instagram;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\SocialProviders\Base\BaseSocialProvider;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;
use App\Services\SocialProviders\Exceptions\SocialProviderException;
use App\Enums\SocialAccountStatus;

/**
 * Instagram Platform API provider (Business Login).
 * Feed image publishing via InstagramPublishingService. Reels/stories: future.
 */
class InstagramProvider extends BaseSocialProvider
{
    public function __construct(
        SocialAccount $account,
        private readonly InstagramOAuthService $oauth,
        private readonly InstagramService $instagramService,
        private readonly InstagramPublishingService $publishing,
    ) {
        parent::__construct($account);
    }

    public function platform(): string
    {
        return 'instagram';
    }

    public function publish(Post $post): PublishResponseDTO
    {
        $this->ensureCapability('publish');
        $this->ensureValidAccount();

        $post->loadMissing('media');

        return $this->publishing->publishFeedPost($this->account, $post);
    }

    public function validateAccount(): bool
    {
        if ($this->account->status !== SocialAccountStatus::Connected) {
            return false;
        }

        if (! filled($this->account->access_token)) {
            return false;
        }

        if ($this->account->isTokenExpired()) {
            return false;
        }

        return $this->oauth->validateAccessToken($this->account->access_token);
    }

    public function refreshToken(): SocialAccount
    {
        $this->ensureCapability('refresh_token');

        return $this->instagramService->refreshToken($this->account);
    }

    /**
     * @return list<string>
     */
    public static function capabilities(): array
    {
        return config('social_providers.capabilities.instagram', [
            'publish',
            'refresh_token',
            'validate_account',
        ]);
    }
}
