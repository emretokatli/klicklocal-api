# Klicklocal — Claude Code Geliştirme Promptları

Bu dosya, audit sonucunda tespit edilen eksiklikleri gidermek için hazırlanmış Claude Code promptlarını içerir.
**Her prompt bağımsız bir oturumda kullanılabilir.** Öncelik sırasına göre sıralanmıştır.

---

## PROMPT 1 — Subscription Gate: Backend Route Koruması + Frontend Paywall

```
You are working on the Klicklocal monorepo at D:\NEWxampp\htdocs\klicklocal.

CONTEXT:
- Laravel 12 backend at backend/
- Next.js 16 frontend at frontend/
- Auth is Sanctum bearer tokens
- Workspace context is set via `workspace.team` middleware (SetWorkspaceTeam)
- EnsureWorkspaceSubscription middleware already exists at backend/app/Http/Middleware/EnsureWorkspaceSubscription.php — it aborts with 402 if no active subscription
- EnsureFeatureQuota middleware already exists but is only applied to 2 routes
- Routes file: backend/routes/api.php

TASK 1 — Backend: Apply subscription middleware to all workspace-scoped routes

Open backend/routes/api.php. Inside the `workspace.team` middleware group (around line 57), add `subscription.required` (alias for EnsureWorkspaceSubscription) to the following route groups:
- All POST/PUT/DELETE on posts
- POST on ai/generate
- POST on media/upload
- POST on subscription (subscribe) should NOT be gated — users need to be able to subscribe first
- GET on billing, subscription show, usage, invoices should NOT be gated — users need to see their status

Register the middleware alias in bootstrap/app.php (or Kernel.php if it exists):
  'subscription.required' => \App\Http\Middleware\EnsureWorkspaceSubscription::class

Also add `feature.quota:ai_generation` middleware to `Route::post('ai/generate', ...)` — the PlanFeature enum already has AiGeneration case.

TASK 2 — Frontend: SubscriptionGate component

Create frontend/src/components/billing/SubscriptionGate.tsx

This component:
- Accepts `children` and optional `fallback` props
- Calls GET /api/v1/subscription (already exists as billingService — check frontend/src/services/billing.service.ts)
- If subscription is null or status is not 'active'/'trialing', renders a paywall UI instead of children:
  - Centered card with a lock icon (use lucide-react Lock icon)
  - Title: "Kein aktives Abonnement"
  - Description: "Bitte wähle einen Plan, um diese Funktion zu nutzen."
  - Button: "Zum Abonnement" linking to /billing
- If loading, render a subtle skeleton (use existing Skeleton component if available, else a div with animate-pulse)
- If active, render children normally

Then wrap the following pages with <SubscriptionGate>:
- frontend/src/app/(dashboard)/ai/page.tsx
- frontend/src/app/(dashboard)/posts/page.tsx
- frontend/src/app/(dashboard)/calendar/page.tsx
- frontend/src/app/(dashboard)/media/page.tsx

The gate should only fire when workspaceId is set. Import useWorkspace from @/store/workspace-context to get workspaceId.

Use existing project patterns: TailwindCSS, shadcn/ui Card component, the de.ts i18n file for any new strings (add under de.billing if needed).

Do not change existing page logic — only wrap with the gate.

Run: cd frontend && npm run build
Fix any TypeScript errors before finishing.
```

---

## PROMPT 2 — Dashboard Kullanım Özeti Widget'ı

```
You are working on the Klicklocal monorepo at D:\NEWxampp\htdocs\klicklocal.

CONTEXT:
- Next.js 16 frontend at frontend/
- billingService.usage(workspaceId) → GET /api/v1/usage returns: { usage: { feature_key: { used, limit, remaining } } }
- billingService.overview(workspaceId) → GET /api/v1/billing returns subscription + plan + usage + features
- UsageMeters component already exists at frontend/src/components/billing/UsageMeters.tsx (inspect it first)
- Dashboard page is at frontend/src/app/(dashboard)/dashboard/page.tsx
- PlanFeature keys relevant here: ai_monthly_tokens, scheduled_posts_monthly, media_uploads_monthly

TASK — Add a UsageSummaryWidget to the Dashboard page

Step 1: Create frontend/src/components/dashboard/UsageSummaryWidget.tsx

This component:
- Takes workspaceId as prop (number | null)
- Fetches billingService.usage(workspaceId) via useQuery(['usage', workspaceId])
- Renders a Card with title "Dein Verbrauch diesen Monat"
- Shows 3 rows for these feature keys (if present in response):
    - scheduled_posts_monthly → label "Geplante Posts"
    - ai_monthly_tokens → label "KI-Generierungen"
    - media_uploads_monthly → label "Media-Uploads"
- Each row: label on left, "X / Y genutzt" on right, and a thin progress bar below (use a simple div with bg-primary/20 + bg-primary fill, width = used/limit * 100%)
- If limit is -1 (unlimited), show "∞" instead of the number
- If no subscription / usage data: show a small EmptyState "Kein aktives Abonnement"
- Bottom of card: small link "Details ansehen →" to /usage

Step 2: Add to Dashboard page

Open frontend/src/app/(dashboard)/dashboard/page.tsx. Add <UsageSummaryWidget workspaceId={workspaceId} /> in the main grid, alongside existing widgets.

Step 3: i18n

Add any new strings to frontend/src/lib/i18n/de.ts under de.dashboard or de.billing as appropriate.

Run: cd frontend && npm run build
Fix TypeScript errors before finishing.
```

---

## PROMPT 3 — AI İçerik Üretimi: Platform & Tür Seçim Wizard'ı

```
You are working on the Klicklocal monorepo at D:\NEWxampp\htdocs\klicklocal.

CONTEXT:
- Backend: POST /api/v1/ai/generate accepts { workspace_id, prompt?, media_id? }
- Backend GenerateContentRequest is at backend/app/Http/Requests/Ai/GenerateContentRequest.php
- AiContentGenerationService generates Instagram caption, story_text, hashtags, call_to_action
- Frontend AI Studio is at frontend/src/components/ai/reel-studio/
- The main /ai page is at frontend/src/app/(dashboard)/ai/page.tsx

TASK 1 — Extend the backend request to accept platform & content_type

Open backend/app/Http/Requests/Ai/GenerateContentRequest.php.
Add optional fields:
- platform: nullable, string, in: instagram, facebook, tiktok, linkedin
- content_type: nullable, string, in: post, reel, story, video

Open backend/app/Services/Ai/AiContentGenerationService.php.
Extend the $data array to accept 'platform' and 'content_type'.
Pass them into the userPrompt() method so the AI prompt says e.g.:
  "Platform: Instagram | Content type: Reel"

TASK 2 — Create a ContentGenerationWizard component on the frontend

Create frontend/src/components/ai/ContentGenerationWizard.tsx

This is a multi-step inline form (not a modal, renders inline in the page) with 3 steps:

Step 1 — "Für welche Plattform?"
  - 4 large toggle buttons: Instagram, Facebook, TikTok, LinkedIn
  - Each with the platform icon (use lucide-react or simple text labels with brand colors)
  - Single select, required

Step 2 — "Welche Art von Inhalt?"
  - Options depend on platform:
    - Instagram/TikTok: Post, Reel, Story
    - Facebook: Post, Video
    - LinkedIn: Post
  - Single select, required

Step 3 — "Zusätzliche Anweisungen (optional)"
  - Textarea for custom prompt (maps to existing `prompt` field)
  - Optional media upload button (maps to existing media_id)
  - "Inhalt generieren" submit button

State management: use local useState for step (1-3) and form values.
On submit, call the existing billingService or a new aiService.generate({ workspace_id, platform, content_type, prompt, media_id }).
After generation, show the result (caption, hashtags, story_text, call_to_action) in a result card below the wizard with copy buttons for each field.

TASK 3 — Wire into the /ai page

Open frontend/src/app/(dashboard)/ai/page.tsx.
Add a tab or toggle at the top: "Reel Studio" | "Post Generator"
- "Reel Studio" tab shows the existing ReelStudio component
- "Post Generator" tab shows the new ContentGenerationWizard

TASK 4 — New aiService

Create frontend/src/services/ai.service.ts with:
  generate(workspaceId, data: { platform?, content_type?, prompt?, media_id? }) → POST /api/v1/ai/generate
  history(workspaceId) → GET /api/v1/ai/generations

Run: cd backend && php artisan test --filter=AiContent
Run: cd frontend && npm run build
Fix all errors before finishing.
```

---

## PROMPT 4 — Ek Paket (Add-on / Top-up) Satın Alma

```
You are working on the Klicklocal monorepo at D:\NEWxampp\htdocs\klicklocal.

CONTEXT:
- Laravel 12 backend at backend/
- Subscription system: Subscription, Plan, PlanFeature, SubscriptionUsage models all exist
- SubscriptionUsageService at backend/app/Services/Billing/SubscriptionUsageService.php handles usage tracking
- Stripe integration exists at backend/app/Services/Billing/Stripe/
- Frontend billing page at frontend/src/app/(dashboard)/billing/page.tsx

TASK 1 — Backend: QuotaAddon model + migration

Create migration: add_quota_addons_table
Table: quota_addons
Columns:
  - id
  - workspace_id (FK workspaces.id)
  - feature_key (string) — matches PlanFeature enum values
  - amount (integer) — extra quota granted
  - expires_at (timestamp, nullable) — null = never expires
  - purchased_at (timestamp)
  - price_paid (decimal 10,2)
  - provider (string, default 'manual') — manual or stripe
  - metadata (json, nullable)
  - timestamps

Create Model: backend/app/Models/QuotaAddon.php
- fillable all columns
- casts: expires_at datetime, metadata array
- workspace() BelongsTo relation
- scopeActive(): where expires_at is null OR expires_at > now()

TASK 2 — Extend SubscriptionUsageService / FeatureAccessService

In SubscriptionUsageService, add method:
  getAddonAmount(Workspace $workspace, PlanFeature $feature): int
  → sums quota_addons where workspace_id = $workspace->id AND feature_key = $feature->value AND (expires_at IS NULL OR expires_at > now())

In FeatureAccessService::canUseFeature(), after getting $limit from plan, add the addon amount:
  $limit = $limit + $this->usageService->getAddonAmount($workspace, $feature);

Also expose in workspaceBillingSummary(): add 'addon' key per feature showing the addon amount.

TASK 3 — Admin: Assign quota add-on endpoint

Create backend/app/Http/Controllers/Api/V1/Admin/QuotaAddonController.php
- store(Request $request): validates { workspace_id, feature_key (in PlanFeature values), amount (int min 1), expires_at (nullable date), price_paid (numeric min 0) }
  Creates QuotaAddon, returns 201
- index(Request $request): lists all addons with workspace name, optional ?workspace_id filter

Register routes in backend/routes/api.php under the admin group:
  Route::get('quota-addons', [QuotaAddonController::class, 'index']);
  Route::post('quota-addons', [QuotaAddonController::class, 'store']);

TASK 4 — Customer: Self-service top-up (fixed packages)

Create backend/app/Http/Controllers/Api/V1/QuotaTopupController.php
- packages(): returns hardcoded top-up packages:
    [
      { key: 'ai_monthly_tokens', label: '50 extra KI-Generierungen', amount: 50, price: 9.99 },
      { key: 'scheduled_posts_monthly', label: '30 extra geplante Posts', amount: 30, price: 4.99 },
    ]
- purchase(Request $request): validates { workspace_id, package_key }
  For now (no Stripe integration required in this task): create the QuotaAddon with provider='manual', expires_at = end of current month, price_paid from package definition
  Return the created addon

Register under authenticated customer routes (with workspace.team middleware):
  Route::get('quota/packages', [QuotaTopupController::class, 'packages']);
  Route::post('quota/topup', [QuotaTopupController::class, 'purchase']);

TASK 5 — Frontend: Top-up section on /billing page

In frontend/src/services/billing.service.ts, add:
  topupPackages(workspaceId) → GET /api/v1/quota/packages
  purchaseTopup(workspaceId, packageKey) → POST /api/v1/quota/topup

In frontend/src/app/(dashboard)/billing/page.tsx, below the usage meters section, add a new Card:
- Title: "Zusätzliche Kontingente kaufen"
- List the packages from topupPackages()
- Each package: label, price, "Kaufen" button
- On click: confirm dialog → call purchaseTopup → invalidate usage/billing queries → show success toast

Run: cd backend && php artisan migrate
Run: cd backend && php artisan test
Run: cd frontend && npm run build
Fix all errors before finishing.
```

---

## PROMPT 5 — Admin Demo Period Yönetimi

```
You are working on the Klicklocal monorepo at D:\NEWxampp\htdocs\klicklocal.

CONTEXT:
- Laravel 12 backend at backend/
- WorkspaceSubscriptionService at backend/app/Services/Billing/WorkspaceSubscriptionService.php
  has a subscribe() method that already handles trial_days from Plan
- Admin subscription controller at backend/app/Http/Controllers/Api/V1/Admin/SubscriptionController.php
- Admin subscriptions frontend page at frontend/src/app/(dashboard)/admin/subscriptions/page.tsx
- SubscriptionStatus enum has Trialing case

TASK 1 — Backend: grantDemoPeriod method

In WorkspaceSubscriptionService, add method:
  grantDemoPeriod(Workspace $workspace, int $days, ?User $actor = null): Subscription

This method:
1. Cancels any existing active/trialing subscription for the workspace
2. Finds the first active Plan with the lowest sort_order (the "starter" plan)
   — if no plan exists, throw a ValidationException: "No active plan found to assign demo to."
3. Creates a Subscription with:
   - status: SubscriptionStatus::Trialing
   - trial_ends_at: now()->addDays($days)
   - ends_at: now()->addDays($days)
   - billing_cycle: 'monthly'
   - provider: BillingProvider::Manual
   - metadata: ['demo' => true, 'granted_by' => $actor?->id]
4. Returns the subscription loaded with plan.features

TASK 2 — Admin endpoint

In backend/app/Http/Controllers/Api/V1/Admin/SubscriptionController.php, add:
  grantDemo(Request $request): JsonResponse
  - validates: { workspace_id (required, exists:workspaces,id), days (required, integer, min:1, max:365) }
  - calls $this->subscriptions->grantDemoPeriod($workspace, $days, $request->user())
  - returns 201 with the subscription

Register in backend/routes/api.php under admin group:
  Route::post('subscriptions/demo', [AdminSubscriptionController::class, 'grantDemo']);
  (add this BEFORE the apiResource line to avoid route conflict)

TASK 3 — Admin Frontend: Demo Period Modal

Open frontend/src/app/(dashboard)/admin/subscriptions/page.tsx (inspect it first to understand current structure).

Add a "Demo vergeben" button in the subscriptions list (or as a standalone section if the page is a simple list).

On click, open a Dialog (use shadcn/ui Dialog) with:
- Title: "Demo-Zeitraum vergeben"
- Workspace selector: dropdown of workspaces (fetch from GET /admin/workspaces)
- Days input: number input, default 14, min 1, max 365, label "Tage"
- Submit button: "Demo starten"
- On success: show toast "Demo-Zeitraum wurde erfolgreich vergeben.", refresh subscription list

Create the API call in a new or existing admin service file.

Run: cd backend && php artisan test
Run: cd frontend && npm run build
Fix all errors before finishing.
```

---

## PROMPT 6 — Sosyal Medyaya Direkt Paylaşım: AI Studio Entegrasyonu

```
You are working on the Klicklocal monorepo at D:\NEWxampp\htdocs\klicklocal.

CONTEXT:
- PostController at backend/app/Http/Controllers/Api/V1/PostController.php handles post CRUD
- POST /api/v1/posts creates a draft post; POST /api/v1/posts/{post}/publish triggers immediate publish
- PostPlatformSyncService and PostPublishingService exist in backend/app/Services/Post/
- SocialAccount model: backend/app/Models/SocialAccount.php
- Social accounts status: GET /api/v1/social-accounts/instagram/status and tiktok/status
- AI generation result has: caption, story_text, hashtags, call_to_action (all strings)
- Frontend: generated content is shown in ContentGenerationWizard result card (created in Prompt 3)
  If Prompt 3 is not done, the sharing UI should be added to frontend/src/components/ai/reel-studio/ReelExportPanel.tsx instead

TASK 1 — Backend: quick-publish endpoint

Create a new endpoint POST /api/v1/posts/quick-publish under the workspace.team + subscription.required middleware group.

Request validation:
  - platform: required, string, in: instagram, tiktok
  - content: required, string (the caption text)
  - media_id: nullable, integer, exists in media table for this workspace

Logic:
1. Check that the workspace has a connected SocialAccount for the given platform
   (SocialAccount where workspace_id = $workspace->id AND platform = $platform AND is_connected = true)
   If not: return 422 with message "Kein verbundenes {platform}-Konto gefunden."
2. Create a Post record with status='publishing', user_id, workspace_id, content
3. If media_id provided, attach it to the post
4. Dispatch PublishPostJob (already exists at backend/app/Jobs/PublishPostJob.php) for the post
5. Return 202 with { post_id, message: "Wird veröffentlicht..." }

Register route:
  Route::post('posts/quick-publish', [PostController::class, 'quickPublish'])
       ->middleware(['subscription.required', 'feature.quota:scheduled_posts_monthly']);

TASK 2 — Frontend: "Jetzt teilen" button in content result

In the content generation result card (wherever the generated caption/hashtags are shown after generation):

Add a "Jetzt teilen" section below the generated content:
- Platform selector: show only connected platforms
  — fetch status from GET /api/v1/social-accounts/instagram/status and tiktok/status
  — show as toggle buttons; grey out unconnected ones with tooltip "Nicht verbunden – Konto verknüpfen"
- "Jetzt auf {platform} teilen" button
- On click: calls POST /api/v1/posts/quick-publish with { platform, content: caption, media_id? }
- Show loading state during request
- On success: green toast "Wird auf Instagram veröffentlicht! 🎉"
- On error: show error message inline

TASK 3 — New postService method

In frontend/src/services (create post.service.ts if it doesn't exist or add to existing):
  quickPublish(workspaceId, data: { platform: string, content: string, media_id?: number }) → POST /api/v1/posts/quick-publish

Run: cd backend && php artisan test
Run: cd frontend && npm run build
Fix all errors before finishing.
```

---

## PROMPT 7 — Müşteri Ödeme Geçmişi (Transaction Sayfası)

```
You are working on the Klicklocal monorepo at D:\NEWxampp\htdocs\klicklocal.

CONTEXT:
- Transaction model: backend/app/Models/Transaction.php
- Admin TransactionController exists at backend/app/Http/Controllers/Api/V1/Admin/TransactionController.php
  (inspect it to understand the data shape)
- No customer-facing transaction endpoint exists yet
- Invoice page exists at frontend/src/app/(dashboard)/invoices/page.tsx (working, has invoice list)
- billingService at frontend/src/services/billing.service.ts

TASK 1 — Backend: customer transactions endpoint

Open backend/app/Services/Billing/BillingService.php (inspect it first).
Add method:
  transactions(Workspace $workspace): Collection
  → returns Transaction::query()->where('workspace_id', $workspace->id)->latest()->get()

Open backend/app/Http/Controllers/Api/V1/BillingController.php.
Add method:
  transactions(Request $request): JsonResponse
  → returns ApiResponse::success(['transactions' => $this->billing->transactions($workspace)])

Register in backend/routes/api.php under workspace.team group (no subscription gate — users should see transactions even without active sub):
  Route::get('transactions', [BillingController::class, 'transactions']);

TASK 2 — Frontend: Transactions page

Create frontend/src/app/(dashboard)/transactions/page.tsx

Pattern: copy the structure of the existing invoices page (frontend/src/app/(dashboard)/invoices/page.tsx).

Display a table with columns:
  - Datum (created_at, formatted de-DE)
  - Beschreibung (description or type column — inspect Transaction model for field names)
  - Betrag (amount, use formatMoney helper)
  - Status (status badge — green for 'paid'/'completed', red for 'failed', yellow for 'pending')
  - Anbieter (provider column if exists)

If no transactions: EmptyState with "Keine Transaktionen vorhanden"

TASK 3 — Add to navigation

Open frontend/src/components/layout/Sidebar.tsx.
In the customerNav array, add after the invoices entry:
  { href: '/transactions', label: de.nav.transactions, icon: Receipt }

Add de.nav.transactions = 'Transaktionen' to frontend/src/lib/i18n/de.ts.

Add to billingService:
  transactions(workspaceId): Promise → GET /api/v1/transactions (with X-Workspace-Id header, same pattern as invoices())

Run: cd frontend && npm run build
Fix TypeScript errors before finishing.
```

---

## PROMPT 8 — Tüm Eksiklikleri Doğrulayan Test Paketi

```
You are working on the Klicklocal monorepo at D:\NEWxampp\htdocs\klicklocal.

CONTEXT:
This is a verification/testing pass after all 7 previous features have been implemented.
Run the full test suite, check for regressions, and fix anything broken.

TASK 1 — Backend tests

Run: cd backend && php artisan test
If any tests fail, fix them. Do not delete tests — only fix the code or update test assertions where the behavior intentionally changed.

Specifically verify these behaviors work correctly:
1. POST /api/v1/ai/generate without an active subscription returns 402 (EnsureWorkspaceSubscription)
2. POST /api/v1/ai/generate with an active subscription but ai_generation feature = false in plan returns 403
3. POST /api/v1/quota/topup creates a QuotaAddon record and the usage remaining increases
4. POST /admin/subscriptions/demo creates a Trialing subscription with metadata.demo = true
5. GET /api/v1/transactions returns only transactions for the requesting workspace

TASK 2 — Frontend build check

Run: cd frontend && npm run build
Fix all TypeScript and build errors.

TASK 3 — Lint

Run: cd frontend && npm run lint
Fix all lint errors (no warnings-as-errors suppression).

TASK 4 — Check CLAUDE.md for any new patterns introduced

If new patterns, models, or routes were added in the previous 7 prompts that are not documented in CLAUDE.md, add them:
- New models: QuotaAddon
- New endpoints: /quota/packages, /quota/topup, /posts/quick-publish, /transactions, /admin/subscriptions/demo, /admin/quota-addons
- New middleware alias: subscription.required
- New frontend pages: /transactions
- New frontend components: SubscriptionGate, UsageSummaryWidget, ContentGenerationWizard

Update the relevant sections of CLAUDE.md (API structure, Frontend routes, Key architecture patterns).
```

---

## Kullanım Sırası

| # | Prompt | Bağımlılık | Süre (tahmini) |
|---|--------|-----------|----------------|
| 1 | Subscription Gate | Yok | ~30 dk |
| 2 | Dashboard Widget | Prompt 1 | ~20 dk |
| 3 | AI Wizard | Prompt 1 | ~45 dk |
| 4 | Add-on Top-up | Prompt 1 | ~45 dk |
| 5 | Demo Period | Prompt 1 | ~30 dk |
| 6 | Social Publish | Prompt 3 | ~40 dk |
| 7 | Transactions | Prompt 1 | ~20 dk |
| 8 | Test & Verify | Tüm promptlar | ~30 dk |

> **Not:** Her promptu ayrı bir `claude` oturumunda çalıştır. Prompt 1'i bitirmeden diğerlerine geçme.
> Claude Code'a her oturumda CLAUDE.md'yi okutmak için oturumu `claude` komutuyla başlat — otomatik olarak CLAUDE.md'yi yükler.
