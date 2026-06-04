<?php

namespace Tests\Unit;

use App\Enums\PostPlatformStatus;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SocialProviders\PostPlatformPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostPlatformPublishingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'social_providers.drivers.instagram' => 'fake',
            'social_providers.fake.success_rate' => 1,
            'social_providers.fake.min_delay_ms' => 0,
            'social_providers.fake.max_delay_ms' => 0,
        ]);
    }

    public function test_publishes_platform_and_stores_platform_post_id(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Pub WS',
            'slug' => 'pub-ws',
        ]);

        $account = SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => 'instagram',
            'provider_account_id' => 'ig-brand',
            'username' => '@brand',
            'access_token' => 'token',
            'status' => \App\Enums\SocialAccountStatus::Connected,
        ]);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'status' => PostStatus::Processing,
        ]);

        $platform = PostPlatform::create([
            'post_id' => $post->id,
            'social_account_id' => $account->id,
            'status' => PostPlatformStatus::Pending,
            'created_at' => now(),
        ]);

        $service = app(PostPlatformPublishingService::class);
        $result = $service->publishForPlatform($post, $platform->fresh('socialAccount'));

        $this->assertTrue($result);
        $platform->refresh();
        $this->assertTrue($platform->isPublished());
        $this->assertNotNull($platform->platform_post_id);
        $this->assertNull($platform->failure_reason);
    }
}
