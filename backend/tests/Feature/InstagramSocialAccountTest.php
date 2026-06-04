<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\OAuthState;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\SocialProviders\Instagram\InstagramProviderSettingsService;
use App\Support\PlatformRole;
use App\Support\TeamContext;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramSocialAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'instagram.enabled' => true,
            'instagram.app_id' => 'ig-app-123',
            'instagram.app_secret' => 'ig-secret-456',
            'instagram.redirect_uri' => 'http://localhost/callback',
            'social_providers.drivers.instagram' => 'api',
        ]);

        Cache::forget('platform.social_providers.instagram');
    }

    public function test_connect_returns_instagram_authorization_url(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'WS',
            'slug' => 'ws-ig',
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner,
            'created_at' => now(),
        ]);
        $this->subscribeWorkspace($workspace);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson(
            '/api/v1/social-accounts/instagram/connect?workspace_id='.$workspace->id,
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['authorization_url']]);

        $url = (string) $response->json('data.authorization_url');
        $this->assertStringContainsString('instagram.com/oauth/authorize', $url);
        $this->assertStringContainsString('client_id=ig-app-123', $url);
        $this->assertDatabaseHas('oauth_states', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'provider' => 'instagram',
        ]);
    }

    public function test_callback_stores_social_account(): void
    {
        Http::fake([
            'https://api.instagram.com/oauth/access_token' => Http::response([
                'access_token' => 'short-token',
                'user_id' => '999',
            ]),
            'https://graph.instagram.com/access_token*' => Http::response([
                'access_token' => 'long-token',
                'expires_in' => 5184000,
            ]),
            'https://graph.instagram.com/*/me*' => Http::response([
                'id' => 'ig-prof-1',
                'user_id' => '999',
                'username' => 'testbrand',
                'name' => 'Test Brand',
                'account_type' => 'BUSINESS',
            ]),
        ]);

        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'WS2',
            'slug' => 'ws-ig-2',
        ]);

        $state = OAuthState::query()->create([
            'state' => 'test-state-abc',
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'provider' => 'instagram',
            'expires_at' => now()->addMinutes(15),
        ]);

        config(['instagram.frontend_redirect' => 'http://localhost:3000/social-accounts']);

        $response = $this->get(
            '/api/v1/social-accounts/instagram/callback?code=auth-code&state='.$state->state,
        );

        $response->assertRedirect();
        $this->assertStringContainsString('instagram=connected', $response->headers->get('Location'));

        $this->assertDatabaseHas('social_accounts', [
            'workspace_id' => $workspace->id,
            'provider' => 'instagram',
            'username' => 'testbrand',
        ]);

        $this->assertDatabaseMissing('oauth_states', ['id' => $state->id]);
    }

    public function test_admin_provider_settings_use_instagram_app_id(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(TeamContext::PLATFORM);

        $admin = User::factory()->create();
        $admin->assignRole(PlatformRole::SUPER_ADMIN);

        app(InstagramProviderSettingsService::class)->update([
            'enabled' => true,
            'app_id' => 'from-cache-id',
        ]);

        $token = $admin->createToken('admin')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/admin/providers');

        $response->assertOk();

        $providers = $response->json('data.providers');
        $instagram = collect($providers)->firstWhere('provider', 'instagram');

        $this->assertNotNull($instagram);
        $this->assertSame('from-cache-id', $instagram['app_id']);
        $this->assertTrue($instagram['has_app_secret']);
    }
}
