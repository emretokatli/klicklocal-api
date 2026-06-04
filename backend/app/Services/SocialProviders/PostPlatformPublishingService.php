<?php

namespace App\Services\SocialProviders;

use App\Enums\PostPlatformStatus;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\Workspace;
use App\Services\SocialProviders\Exceptions\SocialProviderException;
use App\Services\SocialProviders\Factory\SocialProviderFactory;
use App\Services\Usage\UsageTrackingService;
use Illuminate\Support\Facades\Log;
use Throwable;

class PostPlatformPublishingService
{
    public function __construct(
        private readonly SocialProviderFactory $providerFactory,
        private readonly UsageTrackingService $usageTracking,
    ) {}

    public function publishForPlatform(Post $post, PostPlatform $platform): bool
    {
        $account = $platform->socialAccount;

        try {
            $provider = $this->providerFactory->make($account->provider, $account);

            if (! $provider->validateAccount()) {
                $platform->markAsFailed(
                    'Social account failed validation.',
                    ['provider' => $account->provider],
                );

                return false;
            }

            $response = $provider->publish($post);

            if ($response->success) {
                $platform->markAsPublished($response);

                $workspace = Workspace::query()->find($post->workspace_id);
                if ($workspace !== null) {
                    $this->usageTracking->recordSocialApi($workspace, $account->provider);
                }

                Log::info('Post platform published via provider', [
                    'post_id' => $post->id,
                    'post_platform_id' => $platform->id,
                    'provider' => $account->provider,
                    'platform_post_id' => $response->platformPostId,
                ]);

                return true;
            }

            $platform->markAsFailed(
                $response->message ?? 'Provider returned failure.',
                $response->rawResponse,
                $response->platformPostId,
            );

            Log::warning('Post platform publish failed', [
                'post_id' => $post->id,
                'post_platform_id' => $platform->id,
                'message' => $response->message,
            ]);

            return false;
        } catch (SocialProviderException $e) {
            $platform->markAsFailed($e->getMessage());

            Log::error('Social provider error', [
                'post_id' => $post->id,
                'post_platform_id' => $platform->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        } catch (Throwable $e) {
            $platform->markAsFailed($e->getMessage());

            Log::error('Post platform publish exception', [
                'post_id' => $post->id,
                'post_platform_id' => $platform->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
