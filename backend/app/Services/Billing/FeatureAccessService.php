<?php

namespace App\Services\Billing;

use App\Enums\PlanFeature;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;

class FeatureAccessService
{
    public function __construct(
        private readonly SubscriptionUsageService $usageService,
        private readonly WorkspaceSubscriptionService $subscriptions,
    ) {}

    public function activeSubscription(Workspace $workspace): ?Subscription
    {
        return $this->subscriptions->activeForWorkspace($workspace);
    }

    public function canUseFeature(Workspace $workspace, string|PlanFeature $featureKey): bool
    {
        $feature = $this->resolveFeature($featureKey);
        $subscription = $this->activeSubscription($workspace);

        if ($subscription === null) {
            return false;
        }

        $value = $this->getRawFeatureValue($subscription->plan, $feature);

        if ($feature->isBoolean()) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        $limit = (int) $value;

        if ($feature->isUnlimited($limit)) {
            return true;
        }

        return $this->getUsage($workspace, $feature) < $limit;
    }

    public function getFeatureLimit(Workspace $workspace, string|PlanFeature $featureKey): int|bool|null
    {
        $feature = $this->resolveFeature($featureKey);
        $subscription = $this->activeSubscription($workspace);

        if ($subscription === null) {
            return null;
        }

        $value = $this->getRawFeatureValue($subscription->plan, $feature);

        if ($feature->isBoolean()) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return (int) $value;
    }

    public function getUsage(Workspace $workspace, string|PlanFeature $featureKey): int
    {
        return $this->usageService->getUsed($workspace, $this->resolveFeature($featureKey));
    }

    public function incrementUsage(
        Workspace $workspace,
        string|PlanFeature $featureKey,
        int $amount = 1,
    ): void {
        $feature = $this->resolveFeature($featureKey);

        if (! $feature->isBoolean()) {
            $this->usageService->increment($workspace, $feature, $amount);
        }
    }

    public function assertCanUseFeature(Workspace $workspace, string|PlanFeature $featureKey): void
    {
        if (! $this->canUseFeature($workspace, $featureKey)) {
            throw new AuthorizationException(
                'Your plan limit has been reached for: '.$this->resolveFeature($featureKey)->value,
            );
        }
    }

    public function remaining(Workspace $workspace, string|PlanFeature $featureKey): ?int
    {
        $limit = $this->getFeatureLimit($workspace, $featureKey);

        if ($limit === null || is_bool($limit)) {
            return null;
        }

        if ($limit < 0) {
            return null;
        }

        return max(0, $limit - $this->getUsage($workspace, $featureKey));
    }

    /**
     * @return array<string, mixed>
     */
    public function workspaceBillingSummary(Workspace $workspace): array
    {
        $subscription = $this->activeSubscription($workspace);
        $plan = $subscription?->plan;

        $usage = [];
        foreach (PlanFeature::meteredKeys() as $key) {
            $feature = PlanFeature::from($key);
            $usage[$key] = [
                'used' => $this->getUsage($workspace, $feature),
                'limit' => $this->getFeatureLimit($workspace, $feature),
                'remaining' => $this->remaining($workspace, $feature),
            ];
        }

        return [
            'subscription' => $subscription,
            'plan' => $plan,
            'usage' => $usage,
            'features' => $plan ? $this->planFeaturesMap($plan) : [],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function planFeaturesMap(Plan $plan): array
    {
        return Cache::remember(
            'plan.features.'.$plan->id,
            300,
            fn () => $plan->features()->pluck('feature_value', 'feature_key')->all(),
        );
    }

    private function getRawFeatureValue(Plan $plan, PlanFeature $feature): string
    {
        $map = $this->planFeaturesMap($plan);

        return (string) ($map[$feature->value] ?? '0');
    }

    private function resolveFeature(string|PlanFeature $featureKey): PlanFeature
    {
        return $featureKey instanceof PlanFeature
            ? $featureKey
            : PlanFeature::from($featureKey);
    }
}
