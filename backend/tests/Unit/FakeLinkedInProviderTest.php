<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SocialProviders\Fake\FakeLinkedInProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FakeLinkedInProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'social_providers.fake.success_rate' => 1,
            'social_providers.fake.min_delay_ms' => 0,
            'social_providers.fake.max_delay_ms' => 0,
        ]);
    }

    public function test_publish_returns_success_dto(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'LI WS',
            'slug' => 'li-ws',
        ]);

        $account = SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => 'linkedin',
            'provider_account_id' => 'li-brand',
            'username' => 'brand',
            'access_token' => 'secret',
        ]);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'title' => 'Launch',
            'content' => 'Hello world',
            'status' => 'scheduled',
        ]);

        $provider = new FakeLinkedInProvider($account);
        $response = $provider->publish($post);

        $this->assertTrue($response->success);
        $this->assertNotNull($response->platformPostId);
        $this->assertStringStartsWith('linkedin_', $response->platformPostId);
    }

    public function test_validate_account_fails_when_provider_mismatch(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Mismatch WS',
            'slug' => 'mismatch-ws',
        ]);

        $account = SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => 'instagram',
            'provider_account_id' => 'ig-1',
            'username' => 'brand',
        ]);

        $provider = new FakeLinkedInProvider($account);

        $this->assertFalse($provider->validateAccount());
    }
}
