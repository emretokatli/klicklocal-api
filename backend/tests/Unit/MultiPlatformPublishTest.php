<?php

namespace Tests\Unit;

use App\Enums\PostPlatformStatus;
use App\Enums\PostStatus;
use App\Enums\SocialAccountStatus;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SocialProviders\PostPlatformPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiPlatformPublishTest extends TestCase
{
    use RefreshDatabase;

    private function platform(Workspace $workspace, User $user, string $provider): PostPlatform
    {
        $account = SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => $provider,
            'provider_account_id' => $provider.'-'.uniqid(),
            'username' => $provider.'_user',
            'access_token' => 'token',
            'status' => SocialAccountStatus::Connected,
        ]);

        $post = Post::firstOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $user->id, 'content' => 'shared'],
            ['status' => PostStatus::Processing],
        );

        return PostPlatform::create([
            'post_id' => $post->id,
            'social_account_id' => $account->id,
            'status' => PostPlatformStatus::Pending,
            'created_at' => now(),
        ]);
    }

    public function test_instagram_container_timeout_is_retryable_keeps_pending(): void
    {
        config(['social_providers.drivers.instagram' => 'fake']);

        $user = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $user->id, 'name' => 'WS', 'slug' => 'ws-ig-timeout']);
        $platform = $this->platform($workspace, $user, 'instagram');
        $post = $platform->post;

        // Force the provider to return a container-timeout failure.
        $this->mock(\App\Services\SocialProviders\Factory\SocialProviderFactory::class, function ($mock) use ($platform) {
            $provider = \Mockery::mock(\App\Services\SocialProviders\Contracts\SocialProviderInterface::class);
            $provider->shouldReceive('validateAccount')->andReturn(true);
            $provider->shouldReceive('publish')->andReturn(
                \App\Services\SocialProviders\DTOs\PublishResponseDTO::failure(
                    'Container processing took too long. Please try again.',
                ),
            );
            $mock->shouldReceive('make')->andReturn($provider);
        });

        $result = app(PostPlatformPublishingService::class)
            ->publishForPlatform($post, $platform->fresh('socialAccount'));

        $this->assertFalse($result);
        $platform->refresh();
        // Transient → stays Pending for retry, with a recorded reason.
        $this->assertSame(PostPlatformStatus::Pending, $platform->status);
        $this->assertNotNull($platform->failure_reason);
    }

    public function test_facebook_token_expiry_is_terminal_and_expires_account(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $user->id, 'name' => 'WS', 'slug' => 'ws-fb-token']);
        $platform = $this->platform($workspace, $user, 'facebook');
        $post = $platform->post;

        $this->mock(\App\Services\SocialProviders\Factory\SocialProviderFactory::class, function ($mock) {
            $provider = \Mockery::mock(\App\Services\SocialProviders\Contracts\SocialProviderInterface::class);
            $provider->shouldReceive('validateAccount')->andReturn(true);
            $provider->shouldReceive('publish')->andReturn(
                \App\Services\SocialProviders\DTOs\PublishResponseDTO::failure(
                    'Error validating access token: Session has expired.',
                ),
            );
            $mock->shouldReceive('make')->andReturn($provider);
        });

        $result = app(PostPlatformPublishingService::class)
            ->publishForPlatform($post, $platform->fresh('socialAccount'));

        $this->assertFalse($result);
        $platform->refresh();
        $this->assertSame(PostPlatformStatus::Failed, $platform->status);
        $this->assertStringContainsString('erneut', $platform->failure_reason);
        // Account flagged Expired so the UI prompts a reconnect.
        $this->assertSame(SocialAccountStatus::Expired, $platform->socialAccount->fresh()->status);
    }

    public function test_published_platform_is_not_republished_on_reattempt(): void
    {
        config([
            'social_providers.drivers.instagram' => 'fake',
            'social_providers.fake.success_rate' => 1,
            'social_providers.fake.min_delay_ms' => 0,
            'social_providers.fake.max_delay_ms' => 0,
        ]);

        $user = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $user->id, 'name' => 'WS', 'slug' => 'ws-idem']);
        $platform = $this->platform($workspace, $user, 'instagram');
        $post = $platform->post;

        $service = app(PostPlatformPublishingService::class);
        $this->assertTrue($service->publishForPlatform($post, $platform->fresh('socialAccount')));

        $platform->refresh();
        $firstId = $platform->platform_post_id;
        $this->assertTrue($platform->isPublished());

        // PostPublishingService only re-attempts Pending platforms, so a published
        // platform keeps its original platform_post_id (no duplicate publish).
        app(\App\Services\Post\PostPublishingService::class)->publish($post->fresh(['platforms.socialAccount']));

        $platform->refresh();
        $this->assertSame($firstId, $platform->platform_post_id);
    }
}
