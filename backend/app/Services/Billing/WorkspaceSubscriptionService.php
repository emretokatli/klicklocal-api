<?php

namespace App\Services\Billing;

use App\Enums\BillingProvider;
use App\Enums\SubscriptionStatus;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\Stripe\StripeSubscriptionSyncService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class WorkspaceSubscriptionService
{
    public function __construct(
        private readonly StripeSubscriptionSyncService $stripeSync,
    ) {}

    public function activeForWorkspace(Workspace $workspace): ?Subscription
    {
        $subscription = Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Trialing,
                SubscriptionStatus::PastDue,
            ])
            ->with('plan.features')
            ->latest('starts_at')
            ->first();

        if ($subscription === null) {
            return null;
        }

        if ($subscription->status === SubscriptionStatus::PastDue) {
            return $subscription;
        }

        if ($subscription->ends_at && $subscription->ends_at->isPast()) {
            $subscription->update(['status' => SubscriptionStatus::Expired]);

            return null;
        }

        return $subscription->isActive() ? $subscription : null;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function listAll(): Collection
    {
        return Subscription::query()
            ->with(['workspace:id,name,slug', 'plan:id,name,slug'])
            ->latest()
            ->get();
    }

    public function subscribe(
        Workspace $workspace,
        Plan $plan,
        string $billingCycle = 'monthly',
        BillingProvider $provider = BillingProvider::Manual,
        ?User $actor = null,
    ): Subscription {
        Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trialing])
            ->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

        $trialDays = $plan->trial_days;
        $trialEnds = $trialDays > 0 ? now()->addDays($trialDays) : null;

        $subscription = Subscription::create([
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'provider' => $provider,
            'status' => $trialEnds ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
            'billing_cycle' => $billingCycle,
            'trial_ends_at' => $trialEnds,
            'starts_at' => now(),
            'renewal_at' => $billingCycle === 'yearly'
                ? now()->addYear()
                : now()->addMonth(),
            'metadata' => ['assigned_by' => $actor?->id],
        ]);

        return $subscription->load('plan.features');
    }

    public function grantDemoPeriod(Workspace $workspace, int $days, ?User $actor = null): Subscription
    {
        Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trialing])
            ->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

        $plan = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan' => ['No active plan found to assign demo to.'],
            ]);
        }

        $endsAt = now()->addDays($days);

        $subscription = Subscription::create([
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'provider' => BillingProvider::Manual,
            'status' => SubscriptionStatus::Trialing,
            'billing_cycle' => 'monthly',
            'trial_ends_at' => $endsAt,
            'ends_at' => $endsAt,
            'starts_at' => now(),
            'metadata' => ['demo' => true, 'granted_by' => $actor?->id],
        ]);

        return $subscription->load('plan.features');
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        if ($subscription->provider === BillingProvider::Stripe) {
            $this->stripeSync->cancelSubscription($subscription);
        }

        return $subscription->fresh();
    }

    public function applyCoupon(Workspace $workspace, Coupon $coupon, User $user): void
    {
        if (! $coupon->isValid()) {
            throw ValidationException::withMessages([
                'code' => ['This coupon is not valid.'],
            ]);
        }

        if ($coupon->redemptions()->where('workspace_id', $workspace->id)->exists()) {
            throw ValidationException::withMessages([
                'code' => ['Coupon already redeemed for this workspace.'],
            ]);
        }

        $coupon->redemptions()->create([
            'workspace_id' => $workspace->id,
            'redeemed_by' => $user->id,
        ]);

        $coupon->increment('redeemed_count');
    }
}
