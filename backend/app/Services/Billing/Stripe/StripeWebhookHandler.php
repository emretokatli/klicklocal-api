<?php

namespace App\Services\Billing\Stripe;

use App\Enums\BillingProvider;
use App\Enums\SubscriptionStatus;
use App\Enums\TransactionStatus;
use App\Models\Subscription;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\TransactionService;
use Illuminate\Support\Facades\Log;

class StripeWebhookHandler
{
    public function __construct(
        private readonly StripeSubscriptionSyncService $stripeSync,
        private readonly TransactionService $transactions,
        private readonly InvoiceService $invoices,
    ) {}

    public function handle(string $eventType, array $payload): void
    {
        Log::info('Stripe webhook received', ['type' => $eventType]);

        match ($eventType) {
            'customer.subscription.created',
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($payload),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($payload),
            'invoice.paid' => $this->handleInvoicePaid($payload),
            'invoice.payment_failed' => $this->handlePaymentFailed($payload),
            default => null,
        };
    }

    private function handleSubscriptionUpdated(array $payload): void
    {
        $object = $payload['data']['object'] ?? [];
        $stripeId = $object['id'] ?? null;

        if (! $stripeId) {
            return;
        }

        $subscription = Subscription::query()
            ->where('provider_subscription_id', $stripeId)
            ->first();

        if ($subscription === null) {
            return;
        }

        $status = match ($object['status'] ?? '') {
            'active' => SubscriptionStatus::Active,
            'trialing' => SubscriptionStatus::Trialing,
            'past_due' => SubscriptionStatus::PastDue,
            'canceled' => SubscriptionStatus::Cancelled,
            default => $subscription->status,
        };

        $subscription->update([
            'status' => $status,
            'provider' => BillingProvider::Stripe,
            'renewal_at' => isset($object['current_period_end'])
                ? now()->createFromTimestamp($object['current_period_end'])
                : $subscription->renewal_at,
            'metadata' => array_merge($subscription->metadata ?? [], [
                'stripe' => $object,
            ]),
        ]);
    }

    private function handleSubscriptionDeleted(array $payload): void
    {
        $stripeId = $payload['data']['object']['id'] ?? null;

        if (! $stripeId) {
            return;
        }

        Subscription::query()
            ->where('provider_subscription_id', $stripeId)
            ->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
    }

    private function handleInvoicePaid(array $payload): void
    {
        $object = $payload['data']['object'] ?? [];
        $stripeSubId = $object['subscription'] ?? null;

        $subscription = $stripeSubId
            ? Subscription::query()->where('provider_subscription_id', $stripeSubId)->first()
            : null;

        if ($subscription === null) {
            return;
        }

        $amount = ($object['amount_paid'] ?? 0) / 100;

        $this->transactions->record(
            $subscription,
            $amount,
            TransactionStatus::Succeeded,
            $object['payment_intent'] ?? null,
            metadata: ['stripe_invoice' => $object['id'] ?? null],
        );

        $invoice = $this->invoices->create(
            $subscription->workspace,
            $amount,
            $subscription,
            strtoupper($object['currency'] ?? 'eur'),
            $object['invoice_pdf'] ?? null,
        );

        $this->invoices->markPaid($invoice, $object['invoice_pdf'] ?? null);
    }

    private function handlePaymentFailed(array $payload): void
    {
        $stripeSubId = $payload['data']['object']['subscription'] ?? null;

        if (! $stripeSubId) {
            return;
        }

        Subscription::query()
            ->where('provider_subscription_id', $stripeSubId)
            ->update(['status' => SubscriptionStatus::PastDue]);
    }
}
