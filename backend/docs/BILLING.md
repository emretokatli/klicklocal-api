# Billing & Subscriptions (Klicklocal)

## Model

Billing is **workspace-scoped**. Each workspace has one active subscription tied to a `Plan` with normalized `plan_features`.

## Tables

| Table | Purpose |
|-------|---------|
| `plans` | Catalog (prices, trial_days) |
| `plan_features` | Feature keys/limits per plan |
| `subscriptions` | Workspace subscription lifecycle |
| `subscription_usage` | Metered usage per billing period |
| `transactions` | Payment records (Stripe-ready) |
| `invoices` | Workspace invoices |
| `coupons` / `coupon_redemptions` | Discount codes |

## Feature API (`FeatureAccessService`)

```php
$features->canUseFeature($workspace, PlanFeature::AiMonthlyTokens);
$features->getFeatureLimit($workspace, 'storage_limit_mb');
$features->getUsage($workspace, PlanFeature::ScheduledPostsMonthly);
$features->incrementUsage($workspace, PlanFeature::MediaUploadsMonthly);
$features->assertCanUseFeature($workspace, PlanFeature::AiGeneration);
```

## Middleware

| Alias | Usage |
|-------|--------|
| `feature.quota:{key}` | Block request when quota exceeded |
| `workspace.subscription` | Require active subscription |
| `stripe.webhook` | Verify Stripe webhook signature |
| `revenuecat.webhook` | Verify RevenueCat webhook Authorization header |

## Customer API (requires `workspace_id` / `X-Workspace-Id`)

- `GET /api/v1/billing` — overview + usage + plans
- `GET /api/v1/subscription` — current subscription
- `POST /api/v1/subscription` — subscribe to plan
- `POST /api/v1/subscription/cancel`
- `GET /api/v1/usage`
- `GET /api/v1/invoices`

## Admin API

- `GET/POST/PUT/DELETE /api/v1/admin/plans`
- `GET /api/v1/admin/plans/feature-keys`
- `GET/POST/DELETE /api/v1/admin/subscriptions`
- `GET /api/v1/admin/transactions`
- `GET/POST/PUT /api/v1/admin/coupons`
- `GET /api/v1/admin/usage`

## Stripe

- Webhook: `POST /api/v1/webhooks/stripe`
- Configure `STRIPE_WEBHOOK_SECRET`, `STRIPE_KEY`, `STRIPE_SECRET` in `.env`
- Handler: `StripeWebhookHandler` (subscription + invoice events)

## RevenueCat (mobile in-app purchases)

- Webhook: `POST /api/v1/webhooks/revenuecat`
- Configure `REVENUECAT_WEBHOOK_AUTH_TOKEN` in `.env` — must match the Authorization header value set in the RevenueCat dashboard
- Handler: `RevenueCatWebhookService` (`app/Services/Billing/`)
- Mobile calls RevenueCat `logIn()` with the **workspace id**, so `event.app_user_id` maps to a workspace
- Plans map to store products via the nullable `plans.store_product_ids` JSON column (Apple/Google product identifiers, editable through the admin plans API)
- Event ids are stored in `revenuecat_webhook_events`; replayed events are no-ops
- Event → subscription state:

| Event | Result |
|-------|--------|
| `INITIAL_PURCHASE` / `RENEWAL` / `UNCANCELLATION` / `PRODUCT_CHANGE` | Active subscription on the mapped plan, `ends_at`/`renewal_at` from `expiration_at_ms`; transaction recorded for purchase/renewal |
| `CANCELLATION` | Cancel at period end (`cancelled_at` set, status stays `active` until expiry) |
| `EXPIRATION` | Status `expired` — gated endpoints return 402 |
| `BILLING_ISSUE` | Status `past_due` (access retained) + failed transaction recorded |

Unknown workspaces/products are logged and acked with 200 so RevenueCat does not retry indefinitely.

## New workspace

Creating a workspace auto-starts a **Starter** trial subscription when the plan exists in the database.
