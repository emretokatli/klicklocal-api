<?php

namespace Tests\Unit;

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SocialProviders\Exceptions\SocialProviderException;
use App\Services\SocialProviders\Fake\FakeLinkedInProvider;
use App\Services\SocialProviders\Factory\SocialProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialProviderFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_make_resolves_fake_linkedin_provider(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Factory WS',
            'slug' => 'factory-ws',
        ]);

        $account = SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => 'linkedin',
            'provider_account_id' => 'li-123',
            'username' => 'acme',
            'access_token' => 'token',
        ]);

        $provider = app(SocialProviderFactory::class)->make('linkedin', $account);

        $this->assertInstanceOf(FakeLinkedInProvider::class, $provider);
        $this->assertSame('linkedin', $provider->platform());
        $this->assertTrue($provider->supports('publish'));
    }

    public function test_make_throws_for_unsupported_platform(): void
    {
        $this->expectException(SocialProviderException::class);

        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Bad WS',
            'slug' => 'bad-ws',
        ]);

        $account = SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => 'twitter',
            'provider_account_id' => 'tw-123',
            'username' => 'acme',
        ]);

        app(SocialProviderFactory::class)->make('twitter', $account);
    }
}
