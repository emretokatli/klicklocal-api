<?php

namespace App\Services\Billing\Stripe;

use App\Models\Subscription;

/**
 * Stripe API integration stub — wire real Stripe SDK when keys are configured.
 */
class StripeSubscriptionSyncService
{
    public function cancelSubscription(Subscription $subscription): void
    {
        if (! $subscription->provider_subscription_id) {
            return;
        }

        // Stripe::subscriptions()->cancel($subscription->provider_subscription_id);
    }

    public function syncFromWebhook(array $payload): void
    {
        // Map Stripe subscription events to local Subscription model.
    }
}
