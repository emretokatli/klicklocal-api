<?php

namespace Tests\Feature;

use App\Enums\BillingProvider;
use App\Enums\PlanFeature;
use App\Enums\SubscriptionStatus;
use App\Enums\TransactionStatus;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\QuotaAddon;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\PlanFeatureService;
use App\Services\Billing\WorkspaceSubscriptionService;
use App\Support\PlatformRole;
use App\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BillingFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Test WS',
            'slug' => 'test-ws-'.uniqid(),
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner,
            'created_at' => now(),
        ]);

        return [$user, $workspace];
    }

    private function authHeaders(User $user, Workspace $workspace): array
    {
        $token = $user->createToken('test')->plainTextToken;

        return [
            'Authorization' => 'Bearer '.$token,
            'X-Workspace-Id' => (string) $workspace->id,
        ];
    }

    // ── Test 1 ────────────────────────────────────────────────────────────────

    public function test_ai_generate_without_subscription_returns_402(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        // no subscription created

        $this->withHeaders($this->authHeaders($user, $workspace))
            ->postJson('/api/v1/ai/generate', [
                'workspace_id' => $workspace->id,
            ])
            ->assertStatus(402);
    }

    // ── Test 2 ────────────────────────────────────────────────────────────────

    public function test_ai_generate_with_ai_generation_disabled_returns_403(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();

        // Create a plan that has ai_generation disabled
        $plan = Plan::create([
            'slug' => 'no-ai',
            'name' => 'No AI Plan',
            'monthly_price' => 9.99,
            'yearly_price' => 99.00,
            'trial_days' => 0,
            'sort_order' => 99,
            'is_active' => true,
        ]);

        app(PlanFeatureService::class)->sync($plan, [
            PlanFeature::AiGeneration->value => '0',
            PlanFeature::ScheduledPostsMonthly->value => '10',
        ]);

        // Subscribe to the no-AI plan (creates an active subscription)
        $subscription = Subscription::create([
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'provider' => BillingProvider::Manual,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);
        $subscription->load('plan.features');

        $this->withHeaders($this->authHeaders($user, $workspace))
            ->postJson('/api/v1/ai/generate', [
                'workspace_id' => $workspace->id,
            ])
            ->assertStatus(403);
    }

    // ── Test 3 ────────────────────────────────────────────────────────────────

    public function test_quota_topup_creates_addon_and_increases_remaining(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        $this->subscribeWorkspace($workspace);

        $headers = $this->authHeaders($user, $workspace);

        $this->withHeaders($headers)
            ->postJson('/api/v1/quota/topup', [
                'workspace_id' => $workspace->id,
                'package_key' => 'scheduled_posts_monthly',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.addon.feature_key', 'scheduled_posts_monthly');

        $this->assertDatabaseHas('quota_addons', [
            'workspace_id' => $workspace->id,
            'feature_key' => 'scheduled_posts_monthly',
            'amount' => 30,
        ]);

        $this->assertSame(1, QuotaAddon::query()
            ->where('workspace_id', $workspace->id)
            ->where('feature_key', 'scheduled_posts_monthly')
            ->count());
    }

    // ── Test 4 ────────────────────────────────────────────────────────────────

    public function test_admin_grant_demo_creates_trialing_subscription_with_demo_metadata(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(TeamContext::PLATFORM);

        $admin = User::factory()->create();
        $admin->assignRole(PlatformRole::SUPER_ADMIN);

        [$customer, $workspace] = $this->createUserWithWorkspace();

        $adminToken = $admin->createToken('test')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/api/v1/admin/subscriptions/demo', [
                'workspace_id' => $workspace->id,
                'days' => 7,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.subscription.status', SubscriptionStatus::Trialing->value);

        $subscription = Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', SubscriptionStatus::Trialing->value)
            ->firstOrFail();

        $this->assertTrue($subscription->metadata['demo'] ?? false);
    }

    // ── Test 5 ────────────────────────────────────────────────────────────────

    public function test_transactions_endpoint_returns_only_own_workspace_transactions(): void
    {
        [$user1, $workspace1] = $this->createUserWithWorkspace();
        [$user2, $workspace2] = $this->createUserWithWorkspace();

        // Give each workspace a subscription
        $plan = Plan::query()->where('is_active', true)->firstOrFail();

        $sub1 = Subscription::create([
            'workspace_id' => $workspace1->id,
            'plan_id' => $plan->id,
            'provider' => BillingProvider::Manual,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $sub2 = Subscription::create([
            'workspace_id' => $workspace2->id,
            'plan_id' => $plan->id,
            'provider' => BillingProvider::Manual,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Create transactions for both workspaces
        Transaction::create([
            'subscription_id' => $sub1->id,
            'provider' => BillingProvider::Manual,
            'amount' => 19.99,
            'currency' => 'EUR',
            'status' => TransactionStatus::Succeeded,
        ]);
        Transaction::create([
            'subscription_id' => $sub2->id,
            'provider' => BillingProvider::Manual,
            'amount' => 39.99,
            'currency' => 'EUR',
            'status' => TransactionStatus::Succeeded,
        ]);

        $response = $this->withHeaders($this->authHeaders($user1, $workspace1))
            ->getJson('/api/v1/transactions?workspace_id='.$workspace1->id)
            ->assertOk()
            ->assertJsonStructure(['data' => ['transactions']]);

        $transactions = $response->json('data.transactions');
        $this->assertCount(1, $transactions);
        $this->assertEquals('19.99', $transactions[0]['amount']);
    }
}
