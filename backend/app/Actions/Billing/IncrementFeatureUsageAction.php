<?php

namespace App\Actions\Billing;

use App\Enums\PlanFeature;
use App\Models\Workspace;
use App\Services\Billing\FeatureAccessService;

class IncrementFeatureUsageAction
{
    public function __construct(
        private readonly FeatureAccessService $features,
    ) {}

    public function execute(Workspace $workspace, PlanFeature|string $feature, int $amount = 1): void
    {
        $this->features->incrementUsage($workspace, $feature, $amount);
    }
}
