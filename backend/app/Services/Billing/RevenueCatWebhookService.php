<?php

namespace App\Services\Billing;

use App\Enums\BillingProvider;
use App\Enums\SubscriptionStatus;
use App\Enums\TransactionStatus;
use App\Models\Plan;
use App\Models\RevenueCatWebhookEvent;
use App\Models\Subscription;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class RevenueCatWebhookService
{
    public function __construct(
        private readonly TransactionService $transactions,
    ) {}

    public function handle(array $payload): void
    {
        $event = $payload['event'] ?? [];
        $type = $event['type'] ?? 'unknown';
        $eventId = $event['id'] ?? null;

        Log::info('RevenueCat webhook received', ['type' => $type, 'event_id' => $eventId]);

        if ($eventId !== null && RevenueCatWebhookEvent::query()->where('event_id', $eventId)->exists()) {
            Log::info('RevenueCat webhook already processed', ['event_id' => $eventId]);

            return;
        }

        $workspace = $this->resolveWorkspace($event);

        if ($workspace === null) {
            Log::warning('RevenueCat webhook for unknown workspace', [
                'app_user_id' => $event['app_user_id'] ?? null,
                'type' => $type,
            ]);
        } else {
            match ($type) {
                'INITIAL_PURCHASE',
                'RENEWAL',
                'UNCANCELLATION',
                'PRODUCT_CHANGE' => $this->handleActivation($workspace, $event, $type),
                'CANCELLATION' => $this->handleCancellation($workspace, $event),
                'EXPIRATION' => $this->handleExpiration($workspace, $event),
                'BILLING_ISSUE' => $this->handleBillingIssue($workspace, $event),
                default => Log::info('RevenueCat webhook event ignored', ['type' => $type]),
            };
        }

        if ($eventId !== null) {
            RevenueCatWebhookEvent::create(['event_id' => $eventId, 'type' => $type]);
        }
    }

    private function handleActivation(Workspace $workspace, array $event, string $type): void
    {
        $productId = $event['new_product_id'] ?? $event['product_id'] ?? null;
        $plan = $this->resolvePlan($productId);

        if ($plan === null) {
            Log::warning('RevenueCat webhook for unmapped product', [
                'product_id' => $productId,
                'workspace_id' => $workspace->id,
                'type' => $type,
            ]);

            return;
        }

        $startsAt = $this->timestamp($event['purchased_at_ms'] ?? null) ?? now();
        $expiresAt = $this->timestamp($event['expiration_at_ms'] ?? null);

        $subscription = $this->findSubscription($workspace, $event);

        if ($subscription === null) {
            Subscription::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trialing])
                ->update([
                    'status' => SubscriptionStatus::Cancelled,
                    'cancelled_at' => now(),
                ]);

            $subscription = Subscription::create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'provider' => BillingProvider::RevenueCat,
                'status' => SubscriptionStatus::Active,
                'billing_cycle' => $this->billingCycle($startsAt, $expiresAt),
                'starts_at' => $startsAt,
                'ends_at' => $expiresAt,
                'renewal_at' => $expiresAt,
                'cancelled_at' => null,
                'provider_customer_id' => $event['app_user_id'] ?? null,
                'provider_subscription_id' => $event['original_transaction_id'] ?? null,
                'metadata' => ['revenuecat' => $this->eventSummary($event)],
            ]);
        } else {
            $subscription->update([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'billing_cycle' => $this->billingCycle($startsAt, $expiresAt),
                'ends_at' => $expiresAt,
                'renewal_at' => $expiresAt,
                'cancelled_at' => null,
                'provider_customer_id' => $event['app_user_id'] ?? $subscription->provider_customer_id,
                'provider_subscription_id' => $event['original_transaction_id'] ?? $subscription->provider_subscription_id,
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'revenuecat' => $this->eventSummary($event),
                ]),
            ]);
        }

        if (in_array($type, ['INITIAL_PURCHASE', 'RENEWAL'], true)) {
            $this->recordTransaction($subscription, $event, TransactionStatus::Succeeded);
        }
    }

    private function handleCancellation(Workspace $workspace, array $event): void
    {
        $subscription = $this->findSubscription($workspace, $event);

        if ($subscription === null) {
            return;
        }

        // Auto-renew was turned off — access stays until the period ends
        // (EXPIRATION arrives later), so the subscription remains active.
        $subscription->update([
            'cancelled_at' => now(),
            'renewal_at' => null,
            'ends_at' => $this->timestamp($event['expiration_at_ms'] ?? null) ?? $subscription->ends_at,
            'metadata' => array_merge($subscription->metadata ?? [], [
                'revenuecat' => array_merge($this->eventSummary($event), [
                    'cancel_at_period_end' => true,
                    'cancel_reason' => $event['cancel_reason'] ?? null,
                ]),
            ]),
        ]);
    }

    private function handleExpiration(Workspace $workspace, array $event): void
    {
        $subscription = $this->findSubscription($workspace, $event);

        if ($subscription === null) {
            return;
        }

        $subscription->update([
            'status' => SubscriptionStatus::Expired,
            'ends_at' => $this->timestamp($event['expiration_at_ms'] ?? null) ?? now(),
            'cancelled_at' => $subscription->cancelled_at ?? now(),
        ]);
    }

    private function handleBillingIssue(Workspace $workspace, array $event): void
    {
        $subscription = $this->findSubscription($workspace, $event);

        if ($subscription === null) {
            return;
        }

        // Grace period: record the failed charge but keep access (PastDue
        // subscriptions are still returned by activeForWorkspace).
        $subscription->update(['status' => SubscriptionStatus::PastDue]);

        $this->recordTransaction($subscription, $event, TransactionStatus::Failed);
    }

    private function resolveWorkspace(array $event): ?Workspace
    {
        $appUserId = $event['app_user_id'] ?? null;

        if ($appUserId === null || ! ctype_digit((string) $appUserId)) {
            return null;
        }

        return Workspace::find((int) $appUserId);
    }

    private function resolvePlan(?string $productId): ?Plan
    {
        if ($productId === null) {
            return null;
        }

        // Match in PHP — whereJsonContains is unreliable across drivers (SQLite).
        return Plan::query()
            ->whereNotNull('store_product_ids')
            ->get()
            ->first(fn (Plan $plan): bool => in_array($productId, $plan->store_product_ids ?? [], true));
    }

    private function findSubscription(Workspace $workspace, array $event): ?Subscription
    {
        $originalTransactionId = $event['original_transaction_id'] ?? null;

        if ($originalTransactionId !== null) {
            $subscription = Subscription::query()
                ->where('provider', BillingProvider::RevenueCat)
                ->where('provider_subscription_id', $originalTransactionId)
                ->first();

            if ($subscription !== null) {
                return $subscription;
            }
        }

        return Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', BillingProvider::RevenueCat)
            ->latest('starts_at')
            ->first();
    }

    private function recordTransaction(Subscription $subscription, array $event, TransactionStatus $status): void
    {
        $amount = $event['price_in_purchased_currency'] ?? $event['price'] ?? null;

        if ($amount === null) {
            return;
        }

        $this->transactions->record(
            $subscription,
            (float) $amount,
            $status,
            $event['transaction_id'] ?? null,
            BillingProvider::RevenueCat,
            strtoupper($event['currency'] ?? 'EUR'),
            [
                'revenuecat_event_id' => $event['id'] ?? null,
                'revenuecat_event_type' => $event['type'] ?? null,
                'product_id' => $event['product_id'] ?? null,
                'store' => $event['store'] ?? null,
            ],
        );
    }

    private function timestamp(mixed $milliseconds): ?CarbonImmutable
    {
        if (! is_numeric($milliseconds)) {
            return null;
        }

        return CarbonImmutable::createFromTimestampMs((int) $milliseconds);
    }

    private function billingCycle(?CarbonImmutable $startsAt, ?CarbonImmutable $expiresAt): string
    {
        if ($startsAt !== null && $expiresAt !== null && $startsAt->diffInDays($expiresAt) > 200) {
            return 'yearly';
        }

        return 'monthly';
    }

    /**
     * @return array<string, mixed>
     */
    private function eventSummary(array $event): array
    {
        return [
            'event_id' => $event['id'] ?? null,
            'event_type' => $event['type'] ?? null,
            'app_user_id' => $event['app_user_id'] ?? null,
            'product_id' => $event['product_id'] ?? null,
            'new_product_id' => $event['new_product_id'] ?? null,
            'store' => $event['store'] ?? null,
            'environment' => $event['environment'] ?? null,
            'period_type' => $event['period_type'] ?? null,
        ];
    }
}
