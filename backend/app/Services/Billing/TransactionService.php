<?php

namespace App\Services\Billing;

use App\Enums\BillingProvider;
use App\Enums\TransactionStatus;
use App\Models\Subscription;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class TransactionService
{
    /**
     * @return Collection<int, Transaction>
     */
    public function listForSubscription(Subscription $subscription): Collection
    {
        return Transaction::query()
            ->where('subscription_id', $subscription->id)
            ->latest()
            ->get();
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function listAll(?int $limit = 100): Collection
    {
        return Transaction::query()
            ->with(['subscription.workspace:id,name', 'subscription.plan:id,name'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        Subscription $subscription,
        float $amount,
        TransactionStatus $status,
        ?string $providerTransactionId = null,
        BillingProvider $provider = BillingProvider::Stripe,
        string $currency = 'EUR',
        ?array $metadata = null,
    ): Transaction {
        return Transaction::create([
            'subscription_id' => $subscription->id,
            'provider' => $provider,
            'provider_transaction_id' => $providerTransactionId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'metadata' => $metadata,
        ]);
    }
}
