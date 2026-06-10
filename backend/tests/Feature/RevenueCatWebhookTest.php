<?php

namespace Tests\Feature;

use App\Enums\BillingProvider;
use App\Enums\SubscriptionStatus;
use App\Enums\TransactionStatus;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\WorkspaceSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RevenueCatWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH_TOKEN = 'rc-test-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.revenuecat.webhook_auth_token' => self::AUTH_TOKEN]);
    }

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

    private function createMappedPlan(string $productId = 'com.klicklocal.starter.monthly'): Plan
    {
        return Plan::create([
            'slug' => 'rc-plan-'.uniqid(),
            'name' => 'RevenueCat Plan',
            'monthly_price' => 9.99,
            'yearly_price' => 99.00,
            'trial_days' => 0,
            'store_product_ids' => [$productId, 'com.klicklocal.starter.yearly'],
            'sort_order' => 50,
            'is_active' => true,
        ]);
    }

    private function webhookPayload(array $event = []): array
    {
        return [
            'api_version' => '1.0',
            'event' => array_merge([
                'id' => (string) Str::uuid(),
                'type' => 'INITIAL_PURCHASE',
                'app_user_id' => '1',
                'original_app_user_id' => '1',
                'product_id' => 'com.klicklocal.starter.monthly',
                'period_type' => 'NORMAL',
                'purchased_at_ms' => now()->getTimestampMs(),
                'expiration_at_ms' => now()->addMonth()->getTimestampMs(),
                'event_timestamp_ms' => now()->getTimestampMs(),
                'store' => 'APP_STORE',
                'environment' => 'PRODUCTION',
                'price' => 9.99,
                'currency' => 'EUR',
                'price_in_purchased_currency' => 9.99,
                'transaction_id' => 'txn-'.uniqid(),
                'original_transaction_id' => 'orig-txn-1',
            ], $event),
        ];
    }

    private function postWebhook(array $payload, ?string $token = self::AUTH_TOKEN)
    {
        $headers = $token !== null ? ['Authorization' => 'Bearer '.$token] : [];

        return $this->withHeaders($headers)->postJson('/api/v1/webhooks/revenuecat', $payload);
    }

    // ── Auth ────────────────────────────────────────────────────────────────

    public function test_webhook_rejects_missing_or_invalid_authorization(): void
    {
        $this->postJson('/api/v1/webhooks/revenuecat', $this->webhookPayload())
            ->assertStatus(401);

        $this->postWebhook($this->webhookPayload(), 'wrong-token')
            ->assertStatus(401);

        $this->assertSame(0, Subscription::query()->where('provider', BillingProvider::RevenueCat)->count());
    }

    // ── INITIAL_PURCHASE ────────────────────────────────────────────────────

    public function test_initial_purchase_creates_active_subscription_on_mapped_plan(): void
    {
        [, $workspace] = $this->createUserWithWorkspace();
        $plan = $this->createMappedPlan();

        $this->postWebhook($this->webhookPayload([
            'app_user_id' => (string) $workspace->id,
        ]))->assertOk();

        $subscription = Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', BillingProvider::RevenueCat)
            ->firstOrFail();

        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('orig-txn-1', $subscription->provider_subscription_id);
        $this->assertTrue($subscription->ends_at->isFuture());
        $this->assertTrue($subscription->isActive());

        // The existing middleware path must accept this subscription.
        $this->assertNotNull(
            app(WorkspaceSubscriptionService::class)->activeForWorkspace($workspace)
        );

        $transaction = Transaction::query()->where('subscription_id', $subscription->id)->firstOrFail();
        $this->assertSame(BillingProvider::RevenueCat, $transaction->provider);
        $this->assertSame(TransactionStatus::Succeeded, $transaction->status);
        $this->assertEquals('9.99', $transaction->amount);
        $this->assertSame('EUR', $transaction->currency);
    }

    // ── RENEWAL ─────────────────────────────────────────────────────────────

    public function test_renewal_extends_subscription_period(): void
    {
        [, $workspace] = $this->createUserWithWorkspace();
        $this->createMappedPlan();

        $this->postWebhook($this->webhookPayload([
            'app_user_id' => (string) $workspace->id,
        ]))->assertOk();

        $newExpiration = now()->addMonths(2)->getTimestampMs();

        $this->postWebhook($this->webhookPayload([
            'type' => 'RENEWAL',
            'app_user_id' => (string) $workspace->id,
            'expiration_at_ms' => $newExpiration,
        ]))->assertOk();

        $subscription = Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', BillingProvider::RevenueCat)
            ->firstOrFail();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(
            intdiv($newExpiration, 1000),
            $subscription->ends_at->getTimestamp()
        );
        $this->assertSame(2, Transaction::query()->where('subscription_id', $subscription->id)->count());
    }

    // ── CANCELLATION ────────────────────────────────────────────────────────

    public function test_cancellation_marks_cancel_at_period_end_and_retains_access(): void
    {
        [, $workspace] = $this->createUserWithWorkspace();
        $this->createMappedPlan();

        $this->postWebhook($this->webhookPayload([
            'app_user_id' => (string) $workspace->id,
        ]))->assertOk();

        $this->postWebhook($this->webhookPayload([
            'type' => 'CANCELLATION',
            'app_user_id' => (string) $workspace->id,
            'cancel_reason' => 'UNSUBSCRIBE',
        ]))->assertOk();

        $subscription = Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', BillingProvider::RevenueCat)
            ->firstOrFail();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertTrue($subscription->metadata['revenuecat']['cancel_at_period_end']);
        $this->assertTrue($subscription->isActive());
        $this->assertNotNull(
            app(WorkspaceSubscriptionService::class)->activeForWorkspace($workspace)
        );
    }

    // ── EXPIRATION ──────────────────────────────────────────────────────────

    public function test_expiration_revokes_access_and_gated_endpoints_return_402(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        $this->createMappedPlan();

        $this->postWebhook($this->webhookPayload([
            'app_user_id' => (string) $workspace->id,
        ]))->assertOk();

        $this->postWebhook($this->webhookPayload([
            'type' => 'EXPIRATION',
            'app_user_id' => (string) $workspace->id,
            'expiration_at_ms' => now()->subMinute()->getTimestampMs(),
        ]))->assertOk();

        $subscription = Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', BillingProvider::RevenueCat)
            ->firstOrFail();

        $this->assertSame(SubscriptionStatus::Expired, $subscription->status);
        $this->assertNull(
            app(WorkspaceSubscriptionService::class)->activeForWorkspace($workspace)
        );

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Workspace-Id' => (string) $workspace->id,
        ])->postJson('/api/v1/ai/generate', ['workspace_id' => $workspace->id])
            ->assertStatus(402);
    }

    // ── BILLING_ISSUE ───────────────────────────────────────────────────────

    public function test_billing_issue_records_failed_transaction_without_revoking_access(): void
    {
        [, $workspace] = $this->createUserWithWorkspace();
        $this->createMappedPlan();

        $this->postWebhook($this->webhookPayload([
            'app_user_id' => (string) $workspace->id,
        ]))->assertOk();

        $this->postWebhook($this->webhookPayload([
            'type' => 'BILLING_ISSUE',
            'app_user_id' => (string) $workspace->id,
        ]))->assertOk();

        $subscription = Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', BillingProvider::RevenueCat)
            ->firstOrFail();

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->status);
        $this->assertNotNull(
            app(WorkspaceSubscriptionService::class)->activeForWorkspace($workspace)
        );
        $this->assertSame(1, Transaction::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', TransactionStatus::Failed->value)
            ->count());
    }

    // ── Idempotency ─────────────────────────────────────────────────────────

    public function test_duplicate_event_is_a_no_op(): void
    {
        [, $workspace] = $this->createUserWithWorkspace();
        $this->createMappedPlan();

        $payload = $this->webhookPayload([
            'app_user_id' => (string) $workspace->id,
        ]);

        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk();

        $this->assertSame(1, Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', BillingProvider::RevenueCat)
            ->count());
        $this->assertSame(1, Transaction::query()
            ->where('provider', BillingProvider::RevenueCat)
            ->count());
    }

    // ── Unknown data is acked ───────────────────────────────────────────────

    public function test_unknown_workspace_is_acked_without_creating_anything(): void
    {
        $this->createMappedPlan();

        $this->postWebhook($this->webhookPayload([
            'app_user_id' => '999999',
        ]))->assertOk();

        $this->postWebhook($this->webhookPayload([
            'app_user_id' => '$RCAnonymousID:abc123',
        ]))->assertOk();

        $this->assertSame(0, Subscription::query()->where('provider', BillingProvider::RevenueCat)->count());
    }

    public function test_unknown_product_is_acked_without_creating_subscription(): void
    {
        [, $workspace] = $this->createUserWithWorkspace();
        $this->createMappedPlan();

        $this->postWebhook($this->webhookPayload([
            'app_user_id' => (string) $workspace->id,
            'product_id' => 'com.unknown.product',
        ]))->assertOk();

        $this->assertSame(0, Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', BillingProvider::RevenueCat)
            ->count());
    }
}
