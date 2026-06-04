<?php

namespace App\Services\Billing;

use App\Enums\PlanFeature;
use App\Models\SubscriptionUsage;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

class SubscriptionUsageService
{
    public function periodStart(?Workspace $workspace = null): Carbon
    {
        return now()->startOfMonth();
    }

    public function getUsed(Workspace $workspace, PlanFeature $featureKey): int
    {
        $resetAt = $this->periodStart($workspace);

        return (int) SubscriptionUsage::query()
            ->where('workspace_id', $workspace->id)
            ->where('feature_key', $featureKey->value)
            ->where('reset_at', $resetAt)
            ->value('used_value') ?? 0;
    }

    public function increment(Workspace $workspace, PlanFeature $featureKey, int $amount = 1): SubscriptionUsage
    {
        $resetAt = $this->periodStart($workspace);

        $row = SubscriptionUsage::query()->firstOrNew([
            'workspace_id' => $workspace->id,
            'feature_key' => $featureKey->value,
            'reset_at' => $resetAt,
        ]);

        $row->used_value = ($row->used_value ?? 0) + $amount;
        $row->save();

        return $row;
    }

    /**
     * @return array<string, int>
     */
    public function allForWorkspace(Workspace $workspace): array
    {
        $resetAt = $this->periodStart($workspace);

        return SubscriptionUsage::query()
            ->where('workspace_id', $workspace->id)
            ->where('reset_at', $resetAt)
            ->pluck('used_value', 'feature_key')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
