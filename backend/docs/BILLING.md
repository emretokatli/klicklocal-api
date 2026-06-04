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

## New workspace

Creating a workspace auto-starts a **Starter** trial subscription when the plan exists in the database.
