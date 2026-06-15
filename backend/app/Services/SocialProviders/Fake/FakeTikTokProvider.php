<?php

namespace App\Services\SocialProviders\Fake;

use App\Models\Post;
use App\Services\SocialProviders\Base\BaseSocialProvider;
use App\Services\SocialProviders\Contracts\AnalyzesContent;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;
use App\Services\SocialProviders\Fake\Concerns\SimulatesApiPublishing;
use App\Services\SocialProviders\Fake\Concerns\SimulatesContentAnalysis;

class FakeTikTokProvider extends BaseSocialProvider implements AnalyzesContent
{
    use SimulatesApiPublishing;
    use SimulatesContentAnalysis;

    public function platform(): string
    {
        return 'tiktok';
    }

    public function publish(Post $post): PublishResponseDTO
    {
        $this->ensureCapability('publish');
        $this->ensureValidAccount();

        // Simulate creator_info → video/init → status/fetch flow.
        $this->logInfo('Querying TikTok creator info (simulated)', ['post_id' => $post->id]);
        $this->simulateNetworkDelay();

        $audited = (bool) config('tiktok.audited', false);
        $privacy = $audited
            ? (string) (($post->metadata['tiktok']['privacy_level'] ?? '') ?: 'PUBLIC_TO_EVERYONE')
            : 'SELF_ONLY';

        $publishId = 'tiktok_publish_'.bin2hex(random_bytes(8));

        $this->logInfo('TikTok publish initialised (simulated)', [
            'post_id' => $post->id,
            'publish_id' => $publishId,
            'privacy_level' => $privacy,
        ]);

        $successRate = (float) config('social_providers.fake.success_rate', 0.85);
        $shouldSucceed = mt_rand(1, 100) <= (int) ($successRate * 100);

        if (! $shouldSucceed) {
            return $this->failure(
                'Simulated TikTok publish failure.',
                ['post_id' => $post->id, 'publish_id' => $publishId, 'simulated_error' => true],
            );
        }

        return $this->success(
            $publishId,
            'Simulated TikTok publish completed.',
            [
                'post_id' => $post->id,
                'publish_id' => $publishId,
                'privacy_level' => $privacy,
                'status' => 'PUBLISH_COMPLETE',
            ],
        );
    }

    /**
     * @return list<string>
     */
    public static function capabilities(): array
    {
        return config('social_providers.capabilities.tiktok', ['publish']);
    }
}
