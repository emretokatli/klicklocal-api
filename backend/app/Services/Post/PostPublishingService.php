<?php

namespace App\Services\Post;

use App\Contracts\Post\PostPublisherInterface;
use App\Enums\PostPlatformStatus;
use App\Models\Post;
use App\Services\SocialProviders\PostPlatformPublishingService;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        $hadFailure = false;
        $hadSuccess = false;

        foreach ($post->platforms as $platform) {
            try {
                $published = $this->platformPublisher->publishForPlatform($post, $platform);

                if ($published) {
                    $hadSuccess = true;
                } else {
                    $hadFailure = true;
                }
            } catch (Throwable $e) {
                $hadFailure = true;

                if ($platform->isPending()) {
                    $platform->markAsFailed($e->getMessage());
                }

                throw $e;
            }
        }

        $this->finalizePostStatus($post, $hadSuccess, $hadFailure);
    }

    private function publishWithoutPlatforms(Post $post): void
    {
        Log::info('Post published without platform targets (simulated)', [
            'post_id' => $post->id,
            'workspace_id' => $post->workspace_id,
        ]);

        $post->markAsPublished();
    }

    private function finalizePostStatus(Post $post, bool $hadSuccess, bool $hadFailure): void
    {
        $post->refresh();

        $publishedCount = $post->platforms()
            ->where('status', PostPlatformStatus::Published)
            ->count();

        if ($publishedCount > 0) {
            $post->markAsPublished();

            return;
        }

        if ($hadFailure) {
            $post->markAsFailed();
        }
    }
}
