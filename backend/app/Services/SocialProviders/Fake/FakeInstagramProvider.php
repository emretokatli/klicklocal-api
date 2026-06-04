<?php

namespace App\Services\SocialProviders\Fake;

use App\Models\Post;
use App\Services\SocialProviders\Base\BaseSocialProvider;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;
use App\Services\SocialProviders\Fake\Concerns\SimulatesApiPublishing;

class FakeInstagramProvider extends BaseSocialProvider
{
    use SimulatesApiPublishing;

    public function platform(): string
    {
        return 'instagram';
    }

    public function publish(Post $post): PublishResponseDTO
    {
        $this->ensureCapability('publish');
        $this->ensureValidAccount();

        $this->logInfo('Publishing to Instagram (simulated)', [
            'post_id' => $post->id,
        ]);

        return $this->simulatePublish($post);
    }

    /**
     * @return list<string>
     */
    public static function capabilities(): array
    {
        return config('social_providers.capabilities.instagram', ['publish']);
    }
}
