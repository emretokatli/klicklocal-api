<?php

namespace App\Services\SocialProviders\Fake\Concerns;

use App\Models\Post;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;

trait SimulatesApiPublishing
{
    protected function simulatePublish(Post $post): PublishResponseDTO
    {
        $this->simulateNetworkDelay();

        $successRate = (float) config('social_providers.fake.success_rate', 0.85);
        $shouldSucceed = mt_rand(1, 100) <= (int) ($successRate * 100);

        if ($shouldSucceed) {
            $this->logInfo('Simulated publish succeeded', [
                'post_id' => $post->id,
            ]);

            return $this->success(
                message: 'Simulated publish completed successfully.',
                rawResponse: [
                    'post_id' => $post->id,
                    'latency_ms' => $this->lastDelayMs ?? null,
                ],
            );
        }

        $this->logWarning('Simulated publish failed', [
            'post_id' => $post->id,
        ]);

        return $this->failure(
            'Simulated API error: rate limit or transient failure.',
            ['post_id' => $post->id, 'simulated_error' => true],
        );
    }

    protected function simulateNetworkDelay(): void
    {
        $min = (int) config('social_providers.fake.min_delay_ms', 1000);
        $max = (int) config('social_providers.fake.max_delay_ms', 2000);
        $this->lastDelayMs = random_int($min, $max);
        usleep($this->lastDelayMs * 1000);
    }

    protected ?int $lastDelayMs = null;
}
