<?php

namespace Tests\Feature;

use App\Enums\BillingProvider;
use App\Enums\PlanFeature;
use App\Enums\SocialAccountStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\SocialContentAnalysis;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\PlanFeatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialContentAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.driver', 'fake');
        // Use fake social drivers so analytics come from the simulated providers.
        config()->set('social_providers.drivers.instagram', 'fake');
        config()->set('social_providers.drivers.tiktok', 'fake');
        config()->set('social_providers.drivers.facebook', 'fake');
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function createUserWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Analysis WS',
            'slug' => 'analysis-ws-'.uniqid(),
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner,
            'created_at' => now(),
        ]);

        return [$user, $workspace];
    }

    private function connectInstagram(Workspace $workspace): SocialAccount
    {
        return SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => 'instagram',
            'provider_account_id' => 'ig-'.uniqid(),
            'account_name' => 'Brand',
            'username' => 'brand',
            'access_token' => 'token',
            'status' => SocialAccountStatus::Connected,
            'metadata' => ['instagram_user_id' => 'ig-user'],
        ]);
    }

    private function subscribe(Workspace $workspace): void
    {
        $plan = Plan::create([
            'slug' => 'ai-plan-'.uniqid(),
            'name' => 'AI Plan',
            'monthly_price' => 9.99,
            'yearly_price' => 99.00,
            'trial_days' => 0,
            'sort_order' => 97,
            'is_active' => true,
        ]);

        app(PlanFeatureService::class)->sync($plan, [
            PlanFeature::AiGeneration->value => '1',
        ]);

        Subscription::create([
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'provider' => BillingProvider::Manual,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    private function authHeaders(User $user, Workspace $workspace): array
    {
        $token = $user->createToken('test')->plainTextToken;

        return [
            'Authorization' => 'Bearer '.$token,
            'X-Workspace-Id' => (string) $workspace->id,
        ];
    }

    public function test_sync_normalizes_and_stores_media(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        $this->connectInstagram($workspace);

        $response = $this->postJson('/api/v1/social-analysis/sync', [], $this->authHeaders($user, $workspace));

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('data.synced'));
        $this->assertNotEmpty($response->json('data.accounts'));
        $this->assertArrayHasKey('insights', $response->json('data.accounts.0'));

        $rows = SocialContentAnalysis::where('workspace_id', $workspace->id)->get();
        $this->assertGreaterThan(0, $rows->count());

        $row = $rows->first();
        $this->assertSame('instagram', $row->provider);
        $this->assertNotNull($row->post_type);
        $this->assertSame($row->likes + $row->comments + $row->shares, $row->engagement);
        $this->assertNotNull($row->hour);
    }

    public function test_sync_is_idempotent_on_external_id(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        $this->connectInstagram($workspace);

        $headers = $this->authHeaders($user, $workspace);
        $this->postJson('/api/v1/social-analysis/sync', [], $headers)->assertOk();
        $countAfterFirst = SocialContentAnalysis::where('workspace_id', $workspace->id)->count();

        $this->postJson('/api/v1/social-analysis/sync', [], $headers)->assertOk();
        $countAfterSecond = SocialContentAnalysis::where('workspace_id', $workspace->id)->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }

    public function test_content_plan_returns_german_suggestion(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        $this->subscribe($workspace);
        $this->connectInstagram($workspace);

        $headers = $this->authHeaders($user, $workspace);
        $this->postJson('/api/v1/social-analysis/sync', [], $headers)->assertOk();

        $response = $this->postJson('/api/v1/social-analysis/content-plan', [], $headers);

        $response->assertOk();
        $plan = $response->json('data.content_plan');
        $this->assertNotEmpty($plan['summary']);
        $this->assertNotEmpty($plan['best_times']);
        $this->assertNotEmpty($plan['recommended_post_types']);
        $this->assertNotEmpty($plan['content_ideas']);
        $this->assertSame('fake-gpt-5', $plan['model']);
    }

    public function test_content_plan_requires_data(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        $this->subscribe($workspace);

        $response = $this->postJson(
            '/api/v1/social-analysis/content-plan',
            [],
            $this->authHeaders($user, $workspace),
        );

        $response->assertStatus(422);
    }
}
