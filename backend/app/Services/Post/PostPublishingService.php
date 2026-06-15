<?php

namespace App\Services\Post;

use App\Contracts\Post\PostPublisherInterface;
use App\Models\Post;
use App\Services\SocialProviders\PostPlatformPublishingService;
use Illuminate\Support\Facades\Log;

class PostPublishingService implements PostPublisherInterface
{
    public function __construct(
        private readonly PostPlatformPublishingService $platformPublisher,
    ) {}

    public function publish(Post $post): void
    {
        $post->loadMissing(['platforms.socialAccount']);

        if ($post->platforms->isEmpty()) {
            $this->publishWithoutPlatforms($post);

            return;
        }

        // Only attempt platforms that are still pending. Already-published
        // platforms are skipped so a job retry never republishes them; terminal
        // failures stay Failed and are not retried. Transient failures are kept
        // Pending (see PostPlatformPublishingService::markForRetry) and picked up
        // on the next attempt. Post-level finalization is handled by the caller
        // (PublishPostJob), which knows the retry count.
        $pending = $post->platforms->filter(fn ($platform) => $platform->isPending());

        foreach ($pending as $platform) {
            $this->platformPublisher->publishForPlatform($post, $platform);
        }
    }

    private function publishWithoutPlatforms(Post $post): void
    {
        Log::info('Post published without platform targets (simulated)', [
            'post_id' => $post->id,
            'workspace_id' => $post->workspace_id,
        ]);

        $post->markAsPublished();
    }
}
