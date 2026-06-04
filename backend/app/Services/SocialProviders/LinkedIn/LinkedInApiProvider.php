<?php

namespace App\Services\SocialProviders\LinkedIn;

use App\Models\Post;
use App\Services\SocialProviders\Base\BaseSocialProvider;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;
use App\Services\SocialProviders\Exceptions\SocialProviderException;

/**
 * Placeholder for real LinkedIn API integration (OAuth + UGC Posts API).
 */
class LinkedInApiProvider extends BaseSocialProvider
{
    public function platform(): string
    {
        return 'linkedin';
    }

    public function publish(Post $post): PublishResponseDTO
    {
        throw SocialProviderException::missingImplementation('linkedin', 'api');
    }

    /**
     * @return list<string>
     */
    public static function capabilities(): array
    {
        return config('social_providers.capabilities.linkedin', ['publish']);
    }
}
