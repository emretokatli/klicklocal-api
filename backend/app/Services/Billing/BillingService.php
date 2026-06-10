<?php

namespace App\Services\Billing;

use App\Models\Transaction;
use App\Models\Workspace;
use App\Services\Subscription\PlanService;

class BillingService
{
    public function __construct(
        private readonly FeatureAccessService $features,
        private readonly WorkspaceSubscriptionService $subscriptions,
        private readonly PlanService $plans,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(Workspace $workspace): array
    {
        $summary = $this->features->workspaceBillingSummary($workspace);

        return [
            'subscription' => $summary['subscription'],
            'plan' => $summary['plan'],
            'usage' => $summary['usage'],
            'features' => $summary['features'],
            'available_plans' => $this->plans->listActive(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function usage(Workspace $workspace): array
    {
        return $this->features->workspaceBillingSummary($workspace)['usage'];
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Invoice>
     */
    public function invoices(Workspace $workspace)
    {
        return $this->invoices->listForWorkspace($workspace);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Transaction>
     */
    public function transactions(Workspace $workspace)
    {
        $subscriptionIds = $workspace->subscriptions()->pluck('id');

        return Transaction::query()
            ->whereIn('subscription_id', $subscriptionIds)
            ->latest()
            ->get();
    }
}
