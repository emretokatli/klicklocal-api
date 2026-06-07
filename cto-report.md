# Klicklocal — Technical Due Diligence & CTO Audit

**Prepared by:** Acting CTO / Solution Architect / DevOps / Security Auditor / DB Architect / Product Owner
**Date:** 2026-06-06
**Method:** Direct inspection of the codebase (backend, frontend, deploy, migrations, models, controllers, services, middleware, routes, nginx, env templates). No assumptions — every claim below is backed by a file in the repository.
**Repo:** `D:/NEWxampp/htdocs/klicklocal` (git, branch `main`)

> **Bottom line up front:** Klicklocal is a genuinely well-architected Laravel 12 + Next.js 16 SaaS *skeleton* with a working AI content loop and real Instagram OAuth. But it **cannot currently take a single euro, is not legally sellable in Germany, sends no email, and never expires a trial.** This is a strong pre-revenue MVP, not a launch-ready commercial product.

---

# Executive Summary

| Metric | Score | Rationale |
|---|---|---|
| **Project Completion** | **~62%** | Core product loop (signup → profile → AI generate → schedule → publish) is built and structured well. Payments, email, legal, trial enforcement, monitoring are missing. |
| **Launch Readiness** | **~35%** | Multiple hard blockers: no payments, no legal pages, no transactional email, no trial enforcement, Instagram still defaults to fake + needs Meta App Review. |
| **Revenue Readiness** | **~10%** | The product literally **cannot accept money**. No Stripe SDK installed, no Checkout, no card capture, webhook signature verification commented out. |

**Verdict in one line:** Do **not** launch today. ~3–4 weeks of focused work (plus external lead times for Meta App Review and Stripe activation) stand between this codebase and a first paying customer.

---

# Architecture Review

### Strengths

- **Clean, API-first separation.** Laravel owns all business logic; Next.js and Expo are pure clients. Documented and actually enforced in code (`PROJECT.md` architecture rules are respected).
- **Modern, current stack.** Laravel 12 / PHP 8.2, Next.js 16 / React 19, TanStack Query 5, Tailwind 4, Expo 54. No legacy baggage.
- **Strong service-layer discipline.** Thin controllers delegating to services (`app/Services/Ai`, `Billing`, `SocialProviders`, `Post`, `Workspace`). Form Requests used for validation.
- **Provider abstraction done right.** `SocialProviderFactory` + contracts + fake/real drivers means Instagram, Facebook, LinkedIn are swappable; tests can run against fakes.
- **Real, testable AI design.** `OpenAiClientInterface` with `OpenAiClient` (real HTTP, multimodal/image-aware) and `FakeOpenAiClient`, bound via `AppServiceProvider`.
- **Workspace-scoped multi-tenancy** with Spatie permissions + team context, platform roles (`super_admin`, `admin`, `support`) and workspace roles (`owner`, `manager`, `editor`, `viewer`).
- **27 backend tests passing** across auth, workspace, posts, media, admin, Instagram, billing feature access, publishing.

### Weaknesses

- **Payment layer is theater.** `stripe/stripe-php` is **not in `composer.json`**. "Subscribe" creates a local DB row with no money movement.
- **Two disconnected usage systems.** `subscription_usage` (drives quotas) vs `usage_records` (analytics). AI and social API writes only hit `usage_records`, so **AI usage never counts against plan limits** → uncapped cost exposure once the real OpenAI key is set.
- **No scheduler anywhere.** No `php artisan schedule:run` in cron/systemd/supervisor; `routes/console.php` only has the demo `inspire`. Trial expiry, reminders, cleanup cannot run.
- **No Docker** despite the stated stack. Deployment is bare-metal nginx + systemd + supervisor, configured **manually** with no CI/CD.
- **Security headers and TLS live outside version control** (added post-install via Certbot), so config drift is guaranteed.

### Risks

| Risk | Severity | Impact |
|---|---|---|
| Cannot bill customers | **Critical** | Zero revenue path. Entire business model is blocked. |
| Not GDPR/TMG compliant | **Critical** | Operating illegally in DE; liability + Abmahnung risk. |
| Trials never expire | **High** | Everyone uses the product free forever; no conversion pressure. |
| Uncapped AI cost | **High** | A single abusive account can run up an unbounded OpenAI bill. |
| Stripe webhook unverified | **High** | Anyone can POST forged billing events to the endpoint. |
| No backups / monitoring | **High** | Data loss is unrecoverable; outages invisible. |
| Single server, single worker | **Medium** | Scalability ceiling at low hundreds of users. |

---

# Backend Audit

**Stack confirmed:** Laravel `^12.0`, Sanctum `^4.0`, Spatie Permission `^6.0`, L5-Swagger `^11.0`, PHP `^8.2`. Queue = `database`. (`backend/composer.json`)

### Completed ✅

- **Auth:** register / login / logout / me via Sanctum bearer tokens (`AuthController`, `AuthService`). Form Request validation with `Password::defaults()`.
- **Workspaces:** full CRUD, team membership, auto-creates a Starter trial subscription on workspace creation (`WorkspaceService`).
- **Posts:** CRUD + schedule + publish-now; `PublishPostJob` with `tries=3`, `backoff=[30,120,300]`, `failed()` handler.
- **Media:** upload + library with 50 MB limit; quota-gated (`feature.quota:media_uploads_monthly`).
- **AI:** `POST /ai/generate`, `GET /ai/generations`; structured caption/story/hashtags/CTA; real multimodal OpenAI client + deterministic fake; DB-driven prompt templates.
- **Business profile + onboarding:** `business_profiles` (1:1 workspace), `OnboardingController`, `workspaces.onboarding_*` columns.
- **Instagram:** real OAuth (state TTL, token exchange, profile fetch), real Graph publishing (image only), disconnect/status.
- **Billing data model:** plans, plan_features, subscriptions, subscription_usage, transactions, invoices, coupons, coupon_redemptions; local invoice/transaction generation.
- **Admin APIs:** users/roles, plans, subscriptions, transactions, coupons, settings, AI prompts, usage, providers.
- **Authorization:** Spatie roles/permissions + middleware (`platform.admin`, `customer`, `workspace.team`, `feature.quota`).

### Missing ❌

- **Stripe SDK & Checkout** — `stripe/stripe-php` not installed; no Checkout session, PaymentIntent, Customer, or Billing Portal code.
- **Transactional email** — no `app/Mail`, no `app/Notifications`, no Mailables; `config/mail.php` only has `log`/`array`. `MAIL_MAILER=log` everywhere.
- **Password reset flow** — table exists, no routes/controller/email.
- **Email verification** — column exists, unused.
- **Laravel scheduler** — no `Schedule::` definitions, no cron/timer.
- **Trial expiry job** — nothing expires `trial_ends_at`.
- **AI quota enforcement** — `ai/generate` route has no `feature.quota` middleware; service never calls `assertCanUseFeature`.
- **Rate limiting** — no `throttle` on `/auth/*` or `/ai/generate`.
- **Account deletion + data export** (GDPR Art. 17 / 20).
- **Real Facebook / LinkedIn publishing** — Facebook is fake-only; `LinkedInApiProvider::publish()` throws `missingImplementation`.

### Broken / Stubbed 🔴

- **`VerifyStripeWebhook` middleware** — real `\Stripe\Webhook::constructEvent(...)` is **commented out**; always calls `$next()`. Forgeable.
- **`StripeSubscriptionSyncService::syncFromWebhook()`** — empty body; real cancel/sync commented out.
- **Webhook ↔ subscription linkage** — subscriptions are created with `provider = manual` and no `provider_subscription_id`, so even a working webhook would match no rows.
- **`SubscriptionService`** — marked `@deprecated` but still injected into `AuthService`.

### Technical Debt

- **Dual usage tracking** (`subscription_usage` vs `usage_records`) — billing quotas and analytics are not the same source of truth.
- **Inconsistent feature gating** — scheduled posts & media uploads are gated; AI generation and publish-now are not.
- **`workspace.subscription` middleware defined but applied to zero routes** (`bootstrap/app.php`).
- **No `max_tokens` / budget ceiling** on the OpenAI call; no retry/backoff for OpenAI 429s.
- **Default model `gpt-5`** in `config/services.php` may not be a valid OpenAI model ID.
- **No Artisan commands** beyond `inspire`.

---

# Frontend Audit

**Stack confirmed:** Next.js 16.2.6, React 19.2.4, TanStack Query 5, Axios 1.16, Tailwind 4, Radix UI. **25 page routes.** (`frontend/package.json`)

### Completed ✅

- **Marketing landing** with hero, features, workflow, pricing (Pro 39,99 € / Agency 69,99 €, monthly/annual toggle, 14-day trial), FAQ, CTAs (`LandingPage.tsx`).
- **Auth UI:** login, register (→ `/onboarding`), logout.
- **Dashboard shell:** 12 customer pages + 10 admin pages, permission-gated via `ProtectedRoute` / `AdminRoute` / `WorkspaceProvider`.
- **Core product UI:** business profile form, media upload (with progress), AI generator + history (KI-Studio), posts CRUD + schedule + publish-now, subscription/usage/invoice views, Instagram connect.
- **Same-origin API proxy** (`/api/v1/[...path]/route.ts`) forwarding auth + workspace headers; deliberately strips cookies.
- **React Query** with sensible defaults; axios interceptor attaches bearer + `X-Workspace-Id`, handles 401 → `/login`.

### Missing ❌

- **Payment UI** — no `@stripe/stripe-js`, no Checkout/Elements; "Subscribe" is just an API call. **Customers cannot pay.**
- **Legal pages** — no `/impressum`, `/datenschutz`, `/agb`; footer links are **dead anchors** (`#impressum`, etc. with no matching sections). No cookie consent / CMP. No AGB/Datenschutz checkbox on register.
- **Password reset & email verification** pages.
- **Error boundaries** — no `error.tsx`, `global-error.tsx`, `not-found.tsx`; no `query.isError` handling (failed queries render blank).
- **Server-side route protection** — no `middleware.ts`; all guards are client-only (flash-of-content + bypassable).
- **Functional calendar** — `/calendar` is an `EmptyState` placeholder.

### Broken 🔴

- **Landing footer legal links** scroll nowhere (anchors to non-existent sections).
- **Onboarding has no real upload step** ("Lade ein Bild hoch" copy, but `AiGeneratorPanel` only selects existing media); upload lives only on `/media`.
- **No onboarding resume** — backend exposes `onboarding_step`/`onboarding_completed_at` but the frontend ignores them on login.

### Technical Debt

- **Token in `localStorage`** (`lib/token.ts`) — XSS-exfiltratable; not httpOnly cookie.
- **i18n is a single hardcoded `de.ts`** dictionary (~496 lines), not a real i18n framework; landing copy is inline German, not from the dictionary.
- **Default axios base URL hits Laravel directly** if `NEXT_PUBLIC_API_URL` is unset — bypasses the proxy and widens CORS exposure.
- **English product names** ("Pro Mode", "Agency Mode") mixed into German copy.

---

# Database Audit

**Source of truth:** 29 Laravel migrations. MySQL (with SQLite-compat shims for tests).

### Completed ✅

- Core entities: `users`, `workspaces`, `workspace_members`, `posts`, `post_platforms`, `media`, `social_accounts`, `oauth_states`.
- Billing: `plans`, `plan_features`, `subscriptions`, `subscription_usage`, `transactions`, `invoices`, `coupons`, `coupon_redemptions`.
- AI: `business_profiles`, `ai_generations`, `ai_prompt_templates`.
- Laravel: `jobs`, `failed_jobs`, `cache`, `personal_access_tokens`, Spatie permission tables.
- **Good normalization moves:** plan limits moved from JSON blobs into relational `plan_features` (unique `plan_id+feature_key`); subscriptions correctly re-keyed from `user_id` → `workspace_id` with a data backfill migration.
- **Sensible constraints:** cascade deletes, unique invoice numbers, unique coupon code, unique `coupon+workspace` redemption, unique `subscription_usage` per period.

### Missing ❌

- **`notifications` table** — no notification system.
- **Dedicated analytics tables** — only `usage_records`/admin reporting; no per-post social insights (reach, likes, impressions).
- **No `password_reset_tokens` usage** despite the Laravel table.
- **No soft deletes** on key entities — hard cascade deletes make GDPR "right to be forgotten" all-or-nothing and audit trails impossible.

### Normalization Issues

- **Dual usage tables** (`subscription_usage` + `usage_records`) model overlapping concepts → risk of divergence and double-counting.
- **`transactions` keyed only via `subscription_id`** (no direct `workspace_id`); workspace-level financial reporting requires a join through subscriptions, which themselves can be deleted.
- **`provider` strings** ("manual"/"stripe") are free-text varchars rather than enums at the DB level (enforced only in PHP enums).

### Performance Risks

- **`posts` has no composite index on `(status, scheduled_at)`** (`create_posts_table`). Any "due posts" scan or status-filtered listing will table-scan as volume grows. Currently masked because scheduling uses delayed queue jobs, but listing/filtering will degrade.
- **`media`, `posts` listings** rely on FK indexes only; no covering indexes for common `workspace_id + created_at` ordering.
- **`database` queue + `database` cache + `database` sessions** put all hot paths on MySQL — a contention bottleneck under concurrency. Redis is the obvious fix.
- **No pagination guarantees verified** on some admin list endpoints (review for large tenants).

---

# Infrastructure Audit

**Reality:** Bare-metal single Ubuntu host. nginx reverse proxy → Next.js (systemd, :3000/:3001) and Laravel via PHP-FPM 8.3. Queue via Supervisor (1 worker/env). **No Docker, no CI/CD.**

### Completed ✅

- Six nginx vhosts (3 prod + 3 staging) with correct upstreams and `client_max_body_size 50M` (matches Laravel's 50 MB media rule).
- Systemd units for frontend (prod/staging), `Restart=always`.
- Supervisor queue workers (`queue:work --sleep=3 --tries=3 --max-time=3600`).
- Clear two-environment model (branch `main`→prod, `develop`→staging) with separate DBs (`klicklocal_prod` / `klicklocal_staging`), dirs, ports, subdomains.
- Health endpoint `/up`; env-var-driven URLs (no hardcoded localhost in deploy templates).

### Missing ❌

- **No Laravel scheduler** (cron/systemd timer/supervisor) → no trial expiry, reminders, cleanup. **Launch blocker.**
- **No CI/CD** — `.github/workflows` is empty; deploys are manual `git pull` + cache + restart, **no rollback**.
- **No backups** — no `mysqldump`, no off-site, no restore runbook.
- **No monitoring / error tracking** — Sentry/uptime are "future Phase 3" only.
- **No log rotation / aggregation** — files + journald only.
- **No Stripe or real MAIL vars** in any `deploy/env/*.example`.
- **No S3 / CDN** — media on local disk only.

### Security Issues

- **TLS not in version control** — all six vhosts `listen 80` only; HTTPS added manually via Certbot post-install. If skipped, sites serve plain HTTP.
- **Security headers minimal/missing** — API sets only `X-Frame-Options` + `X-Content-Type-Options`; **frontend vhosts set none**. No HSTS, CSP, Referrer-Policy, Permissions-Policy anywhere.
- **No rate limiting** at nginx (`limit_req`) or Laravel route level — brute-force/credential-stuffing open on `/auth/*`.
- **Shared PHP-FPM socket** for prod and staging — no process/resource isolation.
- **Staging can corrupt prod** via human error (shared host, manual `.env`, no automated guard).

### Scalability Issues

- Single box hosts prod + staging + MySQL + 2 Next.js + 2 APIs + 2 workers. Fine for tens of users, strained at hundreds.
- One queue worker/env on `database` driver — publishing backlog grows linearly with concurrent scheduled posts.
- Local disk media + DB-backed cache/session/queue = **no horizontal scaling path** without moving to Redis + S3 and splitting services.

---

# Security Audit

### Critical

1. **Stripe webhook signature verification disabled** (`VerifyStripeWebhook.php` — real verification commented out; always passes through). Forged billing events accepted. *(Moot today since billing is fake, but a live landmine the moment Stripe is enabled.)*
2. **No GDPR/legal foundation** — operating a German B2C SaaS with no Datenschutzerklärung, no Impressum, no AGB, no cookie consent, no consent capture is a legal/compliance breach, not just a UX gap.

### High

3. **No authentication rate limiting** — `/auth/login` and `/auth/register` have no `throttle` (nginx or Laravel). Credential stuffing & enumeration open.
4. **Trials never expire** — `Subscription::isActive()` checks only `ends_at`, never `trial_ends_at`; no expiry job. Indefinite free access.
5. **Uncapped AI cost** — no quota middleware, no `max_tokens`, no rate limit on `/ai/generate`. One actor can drive an unbounded OpenAI bill once the real key is set.
6. **Bearer token in `localStorage`** — XSS-exfiltratable, no httpOnly cookie, and there's **no CSP** to mitigate XSS.
7. **No backups** — ransomware/disk failure = total, unrecoverable data loss.

### Medium

8. **Tenant isolation depends on a client-supplied header** — `SetWorkspaceTeam` reads `X-Workspace-Id`; if absent it passes through without setting team context (controllers must scope correctly — verify every query is workspace-scoped).
9. **Client-only route protection** (no `middleware.ts`) — guards are bypassable; rely entirely on API enforcement.
10. **No security headers** (HSTS/CSP/etc.) on frontend; clickjacking/MIME risks.
11. **Email verification unused** — unverified emails can register and operate.
12. **No account deletion / data export** (GDPR Art. 17/20).

### Low

13. **`APP_DEBUG=true`** in local `.env.example` (production templates correctly `false` — keep it that way).
14. **Default axios base URL** can bypass the proxy if env misconfigured.
15. **Free-text `provider`/`status` columns** instead of DB-level enums.
16. **Secrets hygiene** — local `.env` holds a real `INSTAGRAM_APP_SECRET` and a commented `OPENAI_API_KEY`; gitignored, but ensure never committed and rotate if ever exposed.

---

# Revenue Audit

### Can this product accept payments? — **NO.**

`stripe/stripe-php` is not installed. There is no Checkout session, no PaymentIntent, no card capture, no payment UI (`@stripe/stripe-js` absent from frontend). "Subscribe" writes a `provider = manual` row to the DB with `status = trialing/active` and **no money changes hands**. The webhook that would confirm payment doesn't verify signatures and wouldn't match any subscription anyway. **Revenue path is completely absent.**

### Can this product onboard customers? — **PARTIALLY.**

A user can register, create a workspace (auto-trial), fill a business profile, upload media, generate AI content, and schedule/publish to Instagram. **But:**
- No transactional email → no welcome, no receipts, no password reset (one forgotten password = a permanently locked-out, lost customer).
- No legal pages → cannot legally onboard a German customer.
- Onboarding wizard is fragmented (no in-wizard upload, no resume after login).
- Instagram defaults to the **fake** driver and requires **Meta App Review** before real publishing — the headline value prop isn't live.

### Can this product retain customers? — **NO mechanism today.**

- No trial expiry → no conversion event, no upgrade pressure.
- No email/notifications → no re-engagement, no trial-ending nudges, no publish-success/failure alerts.
- Calendar (a core retention surface for a scheduler) is a placeholder.
- No analytics on post performance → no reason for the customer to come back and see value.
- No customer support channel in-app.

---

# Missing Launch Blockers

> Only the items that block the **first customer**, **first payment**, or **first successful AI-generated post**.

### Blocks the FIRST PAYMENT (hard)
1. **Install `stripe/stripe-php` + implement Stripe Checkout** (create session, redirect, success/cancel handling).
2. **Wire frontend payment UI** (Checkout redirect or Elements) into the billing/subscribe flow.
3. **Implement & enable real Stripe webhook signature verification**, and link subscriptions to `provider_subscription_id`/`provider_customer_id`.

### Blocks the FIRST CUSTOMER (hard)
4. **Transactional email** (SMTP/Resend/Postmark) + German Mailables — enables password reset, receipts, trial reminders.
5. **Password reset flow** (backend routes + email + frontend pages).
6. **GDPR/legal pages:** Impressum, Datenschutzerklärung, AGB, Widerruf + cookie consent banner + AGB/Datenschutz consent checkbox on register.
7. **Trial enforcement** (`isActive()` must honor `trial_ends_at`) **+ a scheduler** (cron/systemd timer running `php artisan schedule:run`) to expire trials and send reminders.

### Blocks the FIRST SUCCESSFUL AI-GENERATED POST (hard)
8. **Set a real `OPENAI_API_KEY`** and confirm `OPENAI_DRIVER=api` in production (with a `max_tokens`/budget cap + quota gate on `/ai/generate`).
9. **Publicly reachable media URLs** (`APP_URL`/storage public) so OpenAI vision + Instagram Graph can fetch images.
10. **Instagram live `api` driver + Meta App Review approval** — *start Day 1; external lead time of weeks.* Until approved, real publishing to customer accounts is not possible.

### Strongly recommended before launch (operational safety)
11. **Auth rate limiting** (nginx `limit_req` + Laravel `throttle`).
12. **Automated DB backups** + a tested restore.
13. **TLS + security headers committed/automated**; basic uptime + error monitoring (Sentry).

---

# Development Roadmap

### Phase 1 — Launch Blockers (~3–4 weeks dev + external lead times)
*Goal: first paying German customer with a real AI Instagram post.*
- Stripe: SDK + Checkout + webhook verification + subscription linkage; add `STRIPE_*` to env templates.
- Transactional email + Mailables + password reset (backend + frontend).
- Trial enforcement in `isActive()` + Laravel scheduler (cron/timer) + trial-ending reminders + expiry job.
- GDPR/legal: Impressum, Datenschutz, AGB, Widerruf, cookie consent, register consent checkboxes; fix dead footer links.
- AI go-live: real OpenAI key, `max_tokens` cap, quota middleware on `/ai/generate`, public storage.
- Instagram: switch to `api` driver + **submit Meta App Review on Day 1**.
- Security: auth rate limiting, commit/automate TLS + security headers, account deletion + data export.
- Ops: automated backups, Sentry + uptime monitoring.

### Phase 2 — First 50 Customers
- Functional calendar; onboarding resume + in-wizard upload.
- Email verification; in-app notifications; publish success/failure alerts.
- Stripe Customer Portal + German invoice PDFs (USt/VAT); dunning for failed payments.
- Error boundaries + consistent `isError` UI; editable profile + change password.
- Unify usage tracking (single source of truth for quotas); enforce AI quota against plan limits.
- Basic Instagram analytics (reach/likes) + dedicated analytics tables.
- Move queue/cache/session to **Redis**; httpOnly-cookie auth + CSP.

### Phase 3 — First 500 Customers / Scale
- Facebook + TikTok publishing (real); team accounts + content approvals.
- Full mobile app (posts/AI/connect/push) — currently auth+workspaces only, English-only.
- Real i18n framework (multi-locale); coupons/referrals self-serve.
- Move media to S3 + CDN; horizontal scaling; CI/CD pipeline with rollback; observability.
- Soft deletes + audit trails; DB-level enums; performance indexing pass.

---

# CTO Verdict

### Would I launch this product today?

**No. Absolutely not.**

### Why?

Because as an investor's technical due-diligence team, I have to separate "impressive engineering" from "a business that can take money," and Klicklocal is firmly the former. The architecture is genuinely good — clean service boundaries, modern stack, a real (not faked) AI client, real Instagram OAuth, a coherent multi-tenant permission model, and 27 passing backend tests. If I were evaluating *engineering quality*, this scores well.

But a SaaS that **cannot accept a single euro** (no Stripe SDK, no Checkout, no card capture), **is not legally allowed to operate in its target market** (no Impressum/Datenschutz/AGB/cookie consent — a hard legal requirement in Germany), **sends zero email** (so a forgotten password permanently loses the customer), and **never ends a trial** (so there is no conversion event even if payments existed) is not a product — it is a polished prototype. On top of that, the flagship value proposition — publishing AI content to Instagram — still **defaults to the fake driver** and is gated behind a **Meta App Review** that hasn't been submitted and takes weeks externally. There are no backups, so the first disk failure is an extinction event, and no monitoring, so you'd learn about outages from angry customers.

None of these are deep architectural problems — they are **finishing work**. That's the good news. The bad news is there are enough of them, with enough external lead time (Meta review, Stripe activation, legal copy), that "launch today" would mean launching something that takes no money, breaks the law, and locks users out.

### What exact tasks must be completed before launch?

**Non-negotiable (in order):**
1. **Start Meta App Review and Stripe activation TODAY** — both have multi-week external lead times and gate everything else.
2. **Stripe end-to-end:** install `stripe/stripe-php`, build Checkout, enable real webhook signature verification, link subscriptions to Stripe IDs, add `STRIPE_*` to env templates, build the frontend payment UI.
3. **Transactional email + password reset** (SMTP/Resend + German Mailables + frontend pages).
4. **Trial enforcement + Laravel scheduler** (cron/systemd timer) so trials expire and reminders send.
5. **GDPR/legal:** Impressum, Datenschutzerklärung, AGB, Widerruf, cookie consent, register consent checkboxes; fix dead footer links.
6. **AI go-live:** real `OPENAI_API_KEY`, `OPENAI_DRIVER=api`, `max_tokens`/budget cap, quota gate on `/ai/generate`, public storage URLs.
7. **Instagram live driver** (post Meta approval).
8. **Security & ops baseline:** auth rate limiting, committed/automated TLS + security headers, automated DB backups + tested restore, Sentry + uptime monitoring, account deletion + data export.

**Realistic timeline to first paying customer:** ~3–4 weeks of engineering, **bounded by the Meta App Review and Stripe activation lead times** — which is precisely why those two must be kicked off on Day 1.

---

*This audit reflects the state of the `main` branch as inspected on 2026-06-06. Every finding is traceable to a specific file in the repository.*
