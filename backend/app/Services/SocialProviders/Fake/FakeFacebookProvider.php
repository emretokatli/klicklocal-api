<?php

namespace App\Services\SocialProviders\Fake;

use App\Models\Post;
use App\Services\SocialProviders\Base\BaseSocialProvider;
use App\Services\SocialProviders\Contracts\AnalyzesContent;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;
use App\Services\SocialProviders\Fake\Concerns\SimulatesApiPublishing;
use App\Services\SocialProviders\Fake\Concerns\SimulatesContentAnalysis;

class FakeFacebookProvider extends BaseSocialProvider implements AnalyzesContent
{
    use SimulatesApiPublishing;
    use SimulatesContentAnalysis;

    public function platform(): string
    {
        return 'facebook';
    }

    public function publish(Post $post): PublishResponseDTO
    {
        $this->ensureCapability('publish');
        $this->ensureValidAccount();

        $this->logInfo('Publishing to Facebook (simulated)', [
            'post_id' => $post->id,
        ]);

        return $this->simulatePublish($post);
    }

    /**
     * @return list<string>
     */
    public static function capabilities(): array
    {
        return config('social_providers.capabilities.facebook', ['publish']);
    }
}
