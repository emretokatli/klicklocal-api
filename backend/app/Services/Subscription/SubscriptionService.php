<?php

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\FeatureAccessService;
use App\Services\Billing\WorkspaceSubscriptionService;
use Illuminate\Support\Collection;

/**
 * @deprecated Prefer WorkspaceSubscriptionService — kept for backward compatibility.
 */
class SubscriptionService
{
    public function __construct(
        private readonly WorkspaceSubscriptionService $workspaceSubscriptions,
        private readonly FeatureAccessService $features,
    ) {}

    public function activeForWorkspace(Workspace $workspace)
    {
        return $this->workspaceSubscriptions->activeForWorkspace($workspace);
    }

    /**
     * @return Collection<int, \App\Models\Subscription>
     */
    public function listAll(): Collection
    {
        return $this->workspaceSubscriptions->listAll();
    }

    public function subscribe(Workspace $workspace, Plan $plan, string $billingCycle = 'monthly')
    {
        return $this->workspaceSubscriptions->subscribe($workspace, $plan, $billingCycle);
    }

    /**
     * @return array<string, int|bool|null>
     */
    public function limitsForWorkspace(Workspace $workspace): array
    {
        $summary = $this->features->workspaceBillingSummary($workspace);
        $limits = [];

        foreach ($summary['usage'] as $key => $row) {
            $limits[$key] = $row['limit'];
        }

        return array_merge([
            'posts_per_month' => $limits['scheduled_posts_monthly'] ?? 50,
            'ai_tokens_per_month' => $limits['ai_monthly_tokens'] ?? 10_000,
            'storage_mb' => $limits['storage_limit_mb'] ?? 512,
        ], $limits);
    }

    /**
     * @deprecated Use limitsForWorkspace
     */
    public function limitsForUser(User $user): array
    {
        $workspace = $user->ownedWorkspaces()->first();

        if ($workspace === null) {
            return [
                'posts_per_month' => 50,
                'ai_tokens_per_month' => 10_000,
                'storage_mb' => 512,
            ];
        }

        return $this->limitsForWorkspace($workspace);
    }
}
