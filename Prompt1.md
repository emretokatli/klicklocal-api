# Klicklocal — MVP Gap Analysis & Launch Roadmap

**Prepared as: Senior PM / SaaS Architect / UX Lead**
**Objective: Ship to the first 10 paying customers (German local businesses) within 30 days**

---

## 0. Executive Verdict

**Can you launch today? No.** You have a solid *scheduling engine* but you do not yet have a *sellable product*. Three things make launch impossible right now, and all three are independent of code quality:

1. **Customers cannot pay you.** There is no Stripe Checkout / payment-method capture. `POST /subscription` only writes a local row with `BillingProvider::Manual`. The Stripe SDK isn't even installed; webhook signature verification and sync are stubbed out. → **You literally cannot collect a euro.**
2. **The core value proposition does not exist.** The product is an *"AI-powered social media assistant,"* but there is **no AI generation**. OpenAI is not wired up (no SDK, no config, zero `openai` references). Only an admin can edit prompt *templates*. A customer cannot generate a single caption.
3. **You are not legally allowed to sell in Germany.** No **Impressum**, no **Datenschutzerklärung**, no **AGB**, no cookie consent. Footer links point to `#impressum` anchors that don't exist. For a German B2B SaaS this is a hard legal blocker (TMG/DDG + GDPR), not a nice-to-have.

Secondary blockers for *real* customers: no password reset, no transactional email (mailer is `log`), Instagram runs in `fake` driver mode and needs Meta App Review, no business profile (so AI would have nothing to personalize on), and no onboarding.

**What IS solid and reusable:** Sanctum auth, workspace model + roles, post CRUD + queue-based scheduling/publishing, media library, Instagram OAuth (real, when enabled), plan/feature/usage metering schema, a German-language web dashboard (hardcoded), EUR currency formatting, and a complete 10-page admin panel.

---

## 1. Current State Snapshot

| Area | Status | Reality |
|---|---|---|
| Register / Login / Logout | ✅ Works | Sanctum tokens, name/email/password |
| Password reset | ❌ Missing | Only the unused Laravel `password_reset_tokens` table |
| Email verification | ❌ Missing | Column exists, never set/enforced |
| Onboarding | ❌ Missing | Dumps user on empty dashboard |
| Business profile / setup | ❌ Missing | Only generic "Workspace" — no business type, brand, address |
| User settings (edit) | 🟡 Read-only | Can't change name/email/password, can't delete account |
| Subscription / Trial | 🟡 Local only | Trial metadata set but **never enforced/expired** |
| Billing / Payment | ❌ Can't pay | No Stripe Checkout, no card capture, SDK not installed |
| AI content generation | ❌ Missing | Admin templates only; OpenAI not wired |
| Social connection | 🟡 IG only | Instagram E2E (defaults to `fake`); FB/TikTok/LinkedIn admin-config only |
| Posts / scheduling / publishing | ✅ Works | Full CRUD + queue jobs |
| Content approval | ❌ Missing | No status, no flow |
| Calendar | 🟡 Placeholder | Empty-state stub only |
| Dashboard analytics | 🟡 Partial | Post-status counts only; no social/reach analytics |
| Notifications | ❌ Missing | No in-app, email, or push |
| Error handling | 🟡 Inconsistent | Good empty states; no error boundaries, spotty loading/fetch-error |
| Mobile app | 🟡 Scaffold | Auth + workspaces only; English-only |
| GDPR / legal | ❌ Missing | No Impressum/Datenschutz/AGB/cookie consent/deletion/export |
| German readiness | 🟡 Web only | Hardcoded German web UI + EUR; no i18n system; mobile/backend English |
| Security | 🟡 Gaps | **No rate limiting on login/register**; webhook verify stubbed |
| Transactional email | ❌ Missing | `MAIL_MAILER=log`, no Mailables |
| Admin panel | ✅ Works | 10 pages, permission-gated |
| Customer support | ❌ Missing | No contact, help, or support channel in-app |

---

## 2. Detailed Gap Analysis

Format per item: **Priority · Why · Implementation · Effort · DB · API · UI**. Effort in dev-days (1 full-stack dev). I focus on what blocks acquiring/retaining paying customers and skip enterprise bloat.

---

### 2.1 Registration flow
- **Priority:** High (works, but unsafe & incomplete)
- **Why:** Open `/register` with no email verification, no rate limiting, no terms acceptance = spam accounts + GDPR consent gap.
- **Implementation:** Add `throttle:6,1` to register route; require `accept_terms` + `accept_privacy` checkboxes (store consent timestamp + version); send welcome email; capture password-confirmation field in UI (backend already requires it).
- **Effort:** 1.5 d
- **DB:** `users.terms_accepted_at`, `users.privacy_accepted_at`, `users.marketing_opt_in` (bool).
- **API:** Extend `RegisterRequest` validation; rate-limit middleware.
- **UI:** Add confirm-password + 2 consent checkboxes with links to AGB/Datenschutz on register page.

### 2.2 Login flow
- **Priority:** High
- **Why:** **No throttling = brute-force risk** on `/api/v1/auth/login`. No "forgot password" link strands users.
- **Implementation:** `throttle:10,1` on login; add "Passwort vergessen?" link; optional remember-me (token TTL).
- **Effort:** 0.5 d
- **DB:** none.
- **API:** rate-limit middleware on login.
- **UI:** forgot-password link on `AuthForm`.

### 2.3 Password reset — **CRITICAL**
- **Priority:** Critical
- **Why:** Without it, any customer who forgets a password is permanently locked out → guaranteed churn + support load. The DB table already exists, only the flow is missing.
- **Implementation:** Use Laravel's password broker. Routes `POST /auth/forgot-password`, `POST /auth/reset-password`. Requires working mailer (2.20).
- **Effort:** 1.5 d (after email is configured)
- **DB:** uses existing `password_reset_tokens`.
- **API:** `POST /auth/forgot-password` (email), `POST /auth/reset-password` (token, email, password).
- **UI:** `/forgot-password` and `/reset-password` pages (German).

### 2.4 Email verification
- **Priority:** Medium (High if abuse appears)
- **Why:** Prevents typo'd emails (so receipts/resets actually arrive) and bot signups. Not a hard launch blocker for 10 hand-picked customers, but cheap insurance.
- **Implementation:** Implement `MustVerifyEmail` on `User`, signed verification route, soft-gate (allow dashboard but banner "Bitte E-Mail bestätigen").
- **Effort:** 1 d
- **DB:** uses existing `email_verified_at`.
- **API:** `POST /auth/email/verify`, `POST /auth/email/resend`.
- **UI:** Verify-email notice banner + resend button.

### 2.5 Business setup flow — **CRITICAL**
- **Priority:** Critical
- **Why:** This is the product's brain. An "AI assistant for restaurants/barbers/salons" must know the business type, brand voice, services, location, and tone — otherwise AI output is generic garbage and onboarding has no payoff. Today only a bare "Workspace" (name) exists.
- **Implementation:** Extend workspace into a **Business Profile**: industry/category (restaurant, café, barber, nail studio, beauty salon, other), business name, city, address, brand tone (e.g. freundlich/professionell/locker), languages, target audience, key offerings, optional logo/colors. Feed this into AI prompts.
- **Effort:** 3 d
- **DB:** Extend `workspaces` (or new `business_profiles`): `industry`, `address`, `city`, `postal_code`, `phone`, `website`, `brand_tone`, `target_audience`, `services` (json), `logo_path`, `brand_color`, `default_language`.
- **API:** `GET/PUT /workspaces/{id}/profile`.
- **UI:** Business-profile form (part of onboarding wizard + editable in settings).

### 2.6 User onboarding flow — **CRITICAL**
- **Priority:** Critical
- **Why:** First-run experience determines activation. Today: signup → empty dashboard → user is lost. For non-technical local-business owners, this kills conversion.
- **Implementation:** 4-step post-signup wizard: (1) Create business + pick industry, (2) Set brand tone/services, (3) Connect Instagram (skippable), (4) Generate first AI post. Track completion to drive activation.
- **Effort:** 3 d
- **DB:** `workspaces.onboarding_completed_at`, `onboarding_step`.
- **API:** reuse profile + social + AI endpoints; `PATCH /workspaces/{id}/onboarding`.
- **UI:** `/onboarding` stepper (German, mobile-first).

### 2.7 Business profile management
- **Priority:** High
- **Why:** Owners need to edit business info, brand tone, logo after onboarding (rebrands, new services). Drives ongoing AI quality.
- **Implementation:** Editable form reusing 2.5 schema in workspace settings.
- **Effort:** 1 d (after 2.5)
- **DB:** same as 2.5.
- **API:** same as 2.5.
- **UI:** Business settings tab.

### 2.8 Subscription flow — **CRITICAL**
- **Priority:** Critical
- **Why:** No real subscription = no revenue. Current flow creates a local "Manual" subscription with no payment.
- **Implementation:** Stripe Checkout Session (hosted) per plan; on success redirect → confirm via webhook → activate subscription. Use Stripe price IDs on plans.
- **Effort:** 4 d (with 2.9)
- **DB:** `plans.stripe_price_id`, `subscriptions.stripe_subscription_id`, `stripe_customer_id`, `workspaces.stripe_customer_id`.
- **API:** `POST /billing/checkout` (returns Checkout URL), harden `POST /webhooks/stripe`.
- **UI:** Plans page → "Jetzt abonnieren" → Stripe redirect → success/cancel pages.

### 2.9 Billing requirements — **CRITICAL**
- **Priority:** Critical
- **Why:** Need to actually charge, verify webhooks (currently stubbed/commented), generate compliant invoices (German invoices need seller details, VAT/USt, sequential number), and let users manage payment/cancel.
- **Implementation:** Install `stripe/stripe-php`; enable real signature verification in `VerifyStripeWebhook`; implement Stripe Customer Portal link for payment-method/cancel; ensure invoices store legally-required fields. Configure VAT handling (Stripe Tax or reverse-charge for B2B).
- **Effort:** included in 2.8 + 1 d for portal/invoice fields
- **DB:** invoice fields: `invoice_number` (sequential), `vat_rate`, `vat_amount`, `seller_details`, `pdf_path`.
- **API:** `GET /billing/portal` (Stripe portal URL); webhook handlers for `invoice.paid`, `customer.subscription.updated/deleted`.
- **UI:** "Zahlungsmethode verwalten" button, invoice list with downloadable PDF.

### 2.10 Trial flow
- **Priority:** Critical
- **Why:** Trial is set (`trial_ends_at`) but **never enforced or expired** — `isActive()` ignores it, no expiry job. Trials that never end = never convert to paid.
- **Implementation:** Include `trial_ends_at` in `isActive()`; scheduled command to expire trials and send "trial ending in 3 days / ended" emails; gate features when expired; prompt to add payment.
- **Effort:** 2 d
- **DB:** uses existing fields; add `subscriptions.trial_reminded_at`.
- **API:** trial status surfaced in `GET /billing`.
- **UI:** Trial banner ("Noch X Tage Testphase") + upgrade CTA.

### 2.11 AI content generation flow — **CRITICAL**
- **Priority:** Critical
- **Why:** The entire product. Without it you're a worse Buffer. Must turn business profile + a short idea into ready-to-post captions (with hashtags, German, on-brand tone), optionally weekly plans.
- **Implementation:** Install OpenAI PHP client; `AiGenerationService` that merges active prompt template + business profile + user input → OpenAI → caption variants. Meter `ai_monthly_tokens` (enum already exists) via `feature.quota`. Cache/store generations.
- **Effort:** 4 d
- **DB:** `ai_generations` (workspace_id, prompt, result, tokens_used, type, created_at).
- **API:** `POST /ai/generate` (type: caption|weekly_plan|reply, input, tone) guarded by `feature.quota:ai_monthly_tokens`.
- **UI:** "✨ Mit AI generieren" button in post composer → idea input + tone → 3 caption options → insert. AI panel in onboarding step 4.

### 2.12 Social account connection flow
- **Priority:** Critical (Instagram), Low (others)
- **Why:** Publishing is core. Instagram OAuth is real but defaults to `fake` driver, and **Meta App Review** is required before real businesses can connect. FB/TikTok/LinkedIn are out of scope for first 10.
- **Implementation:** Flip Instagram to `api` driver in prod; complete Meta App Review (needs public Privacy Policy + Data Deletion URL — see 2.18); robust connect/error UX; reconnect on token expiry.
- **Effort:** 2 d code + Meta review lead time (start NOW, can take 1–3 weeks).
- **DB:** existing `social_accounts`; add `token_expires_at`, `needs_reconnect`.
- **API:** existing IG routes; add token-refresh handling.
- **UI:** connection status, reconnect prompts, clear error states.

### 2.13 Content approval flow
- **Priority:** Low (for 10 customers)
- **Why:** Single-owner businesses don't need multi-step approval. Skip until team accounts matter (Phase 3).
- **Implementation:** Defer.
- **Effort:** — (Phase 3)
- **DB/API/UI:** later.

### 2.14 Content scheduling flow
- **Priority:** High
- **Why:** Backend scheduling works; the **calendar UI is a placeholder**. Local owners want to *see* their week visually; it's a key retention/"wow" surface.
- **Implementation:** Build month/week calendar consuming existing posts API; drag-to-reschedule (calls `PUT /posts/{id}`); "best time" suggestions later.
- **Effort:** 3 d
- **DB:** none.
- **API:** existing posts endpoints.
- **UI:** Calendar view with scheduled posts, click-to-edit.

### 2.15 Publishing flow
- **Priority:** High
- **Why:** Queue publishing exists but needs production hardening: failure handling, retries, and **failure notifications** (today a failed post is silent → trust killer).
- **Implementation:** Retry/backoff on `PublishPostJob`; on permanent failure set `failed` + notify user (email + in-app); surface error reason in UI.
- **Effort:** 2 d
- **DB:** `posts.failure_reason`, `posts.published_at` (if absent).
- **API:** include failure reason in post resource.
- **UI:** failed-post badge + "erneut versuchen" button.

### 2.16 Dashboard analytics
- **Priority:** Medium
- **Why:** Owners want to see it's working (posts published, upcoming, simple reach later). Current dashboard shows post-status counts only — fine for launch; add light value.
- **Implementation:** Phase 1: keep status counts + "next scheduled" + trial/quota widgets. Phase 2: pull basic IG insights (reach/likes) into a simple analytics table.
- **Effort:** 1 d (P1 polish) / 3 d (P2 IG insights)
- **DB:** (P2) `post_metrics` (post_id, reach, likes, comments, fetched_at).
- **API:** (P2) `GET /analytics`, IG insights fetch job.
- **UI:** dashboard widgets; later analytics page.

### 2.17 User settings
- **Priority:** High
- **Why:** Settings is read-only. Users must change password, name, email; required for GDPR self-service and basic trust.
- **Implementation:** Editable profile + change-password; integrate account deletion (2.18).
- **Effort:** 1.5 d
- **DB:** none new.
- **API:** `PUT /auth/profile`, `PUT /auth/password`.
- **UI:** editable settings forms.

### 2.18 GDPR compliance — **CRITICAL**
- **Priority:** Critical
- **Why:** Legally mandatory to operate in Germany. Missing: Datenschutzerklärung, cookie consent, data export (DSAR), account deletion (Art. 17), data-processing records, Meta data-deletion callback (also blocks App Review).
- **Implementation:** Privacy policy page; cookie consent banner (only load analytics after consent); self-service account deletion (soft-delete + purge job); data export (JSON download); Meta data-deletion endpoint; AV/DPA note. Use a German legal template / lawyer review.
- **Effort:** 3 d engineering + legal review
- **DB:** `users.deleted_at` (soft delete), `data_export_requests` (optional).
- **API:** `DELETE /auth/account`, `GET /auth/data-export`, `POST /webhooks/meta/data-deletion`.
- **UI:** `/datenschutz`, cookie banner, "Konto löschen" + "Meine Daten exportieren" in settings.

### 2.19 German market readiness
- **Priority:** High
- **Why:** Web is German (hardcoded), EUR ✅. Gaps: legal pages (2.18), German transactional emails, German error messages, EU data residency expectation, SEPA/German payment methods via Stripe.
- **Implementation:** Ensure all emails + Stripe Checkout locale = German; enable SEPA Direct Debit + cards in Stripe; backend `APP_LOCALE=de` for validation messages users may see.
- **Effort:** 1 d
- **DB:** none.
- **API:** locale on mailables.
- **UI:** German throughout (mostly done).

### 2.20 Transactional email — **CRITICAL (enabler)**
- **Priority:** Critical
- **Why:** Mailer is `log` — **nothing sends**. Blocks password reset, verification, receipts, trial reminders, publish-failure alerts. Nothing else in 2.3/2.10/2.15 works without it.
- **Implementation:** Configure SMTP/Resend/Postmark; create German Mailables (welcome, reset, verify, trial-ending, payment-receipt, publish-failed); use a queued, branded template.
- **Effort:** 1.5 d
- **DB:** none.
- **API:** none.
- **UI:** email templates.

### 2.21 Notifications (in-app)
- **Priority:** Medium
- **Why:** Email covers launch-critical alerts. In-app notification center is a Phase-2 retention nicety (publish success/failure, trial, new features). Push (mobile) is later.
- **Implementation:** Laravel `notifications` table + `GET /notifications`, bell UI. Email channel first (2.20), database channel second.
- **Effort:** 2 d
- **DB:** standard Laravel `notifications` table.
- **API:** `GET /notifications`, `POST /notifications/{id}/read`.
- **UI:** bell + dropdown.

### 2.22 Error handling
- **Priority:** High
- **Why:** No route-level `error.tsx`/error boundaries; inconsistent loading/fetch-error states. Non-technical users hitting a blank/broken screen churn.
- **Implementation:** Add global `error.tsx` + `loading.tsx`, React error boundary, standardize React Query `isError`/`isLoading` handling, friendly German error messages + retry.
- **Effort:** 2 d
- **DB:** none.
- **API:** none.
- **UI:** error/loading states across pages.

### 2.23 Empty states
- **Priority:** Low (mostly done)
- **Why:** `EmptyState` is good and widely used. Just ensure every customer page (calendar, analytics, AI) has a helpful, action-oriented empty state.
- **Effort:** 0.5 d
- **UI:** fill gaps.

### 2.24 Mobile experience
- **Priority:** Low for launch (web-first)
- **Why:** Mobile is auth+workspaces only, English. For 10 pilot customers, web is enough. Don't sink 30 days into mobile parity now. Keep it logged-in but defer features to Phase 2/3.
- **Implementation:** Phase 2: posts list + AI generate + Instagram connect on mobile, German strings, NativeWind, React Query (matches your stack rules).
- **Effort:** 8–10 d (Phase 2/3)
- **UI:** post composer, AI panel, social connect.

### 2.25 Security concerns
- **Priority:** Critical (specific items)
- **Why:** **No rate limiting on auth** (brute-force), webhook signature verification **stubbed/commented**, token in `localStorage` (XSS exposure). These are launch blockers for handling real customer data/payments.
- **Implementation:** Throttle auth routes; enable real Stripe webhook signature check; security headers (HSTS, CSP basics) at proxy/Nginx; ensure HTTPS-only; review CORS for prod origins.
- **Effort:** 1.5 d
- **DB:** none.
- **API:** throttle + webhook verify.
- **UI:** none.

### 2.26 Admin panel requirements
- **Priority:** Medium (mostly done)
- **Why:** 10 pages exist. Gaps for running a real business: impersonate/support a customer, see failed publishes, manual refund/credit, view AI usage per workspace.
- **Implementation:** Add "view as / support" read context, AI usage report, failed-publish monitor.
- **Effort:** 2 d (Phase 2)
- **API:** admin read endpoints.
- **UI:** admin additions.

### 2.27 Customer support requirements
- **Priority:** High
- **Why:** First 10 customers need a way to reach you fast or they churn silently. No in-app contact/help today.
- **Implementation:** Simple support email/WhatsApp link + embedded contact form (or Crisp/Tawk widget); minimal German FAQ/help page; onboarding "Brauchst du Hilfe?" CTA.
- **Effort:** 1 d
- **DB:** optional `support_messages`.
- **API:** optional `POST /support/contact`.
- **UI:** Help/contact link in sidebar + landing.

---

## 3. Phased Plan

### PHASE 1 — Must Have Before Launch (first 10 paying customers)
*Goal: legally compliant, can charge money, delivers the AI promise, won't lock users out.*

| # | Item | Priority | Est. |
|---|---|---|---|
| 1 | Transactional email (enabler) | Critical | 1.5 d |
| 2 | Stripe Checkout + real payments + webhook verify | Critical | 4 d |
| 3 | Trial enforcement + expiry + reminders | Critical | 2 d |
| 4 | AI content generation (OpenAI + profile-aware) | Critical | 4 d |
| 5 | Business profile/setup (industry, brand tone, services) | Critical | 3 d |
| 6 | Onboarding wizard | Critical | 3 d |
| 7 | Password reset | Critical | 1.5 d |
| 8 | GDPR: Impressum, Datenschutz, AGB, cookie consent, account deletion, data export, Meta deletion callback | Critical | 3 d |
| 9 | Security: auth rate limiting + webhook verify + HTTPS/headers | Critical | 1.5 d |
| 10 | Instagram → live `api` driver + start Meta App Review | Critical | 2 d (+review lead time) |
| 11 | User settings editable (name/email/password) | High | 1.5 d |
| 12 | Publishing hardening + failure notifications | High | 2 d |
| 13 | Customer support channel (contact + help) | High | 1 d |
| 14 | Registration/login consent + confirm-password + throttle | High | 1.5 d |
| 15 | Error boundaries + loading/error states | High | 2 d |

**Phase 1 total: ~33 dev-days** (≈ 30 calendar days with one strong dev, or ~3 weeks with two). **Start Meta App Review on Day 1** — it's the longest external dependency.

### PHASE 2 — Needed For First 50 Customers
*Goal: retention, self-service, less support load, polish.*

| Item | Priority | Est. |
|---|---|---|
| Calendar view (visual scheduling) | High | 3 d |
| Email verification | Medium | 1 d |
| In-app notification center | Medium | 2 d |
| Basic Instagram analytics (reach/likes) | Medium | 3 d |
| Stripe Customer Portal + German invoice PDFs (USt) | High | 2 d |
| AI improvements: weekly plan, hashtag/emoji tuning, regenerate | Medium | 3 d |
| Mobile: posts + AI generate + IG connect (German) | Medium | 8 d |
| Admin: AI usage report, failed-publish monitor, support view | Medium | 2 d |
| German FAQ / help center | Medium | 1.5 d |

### PHASE 3 — Needed For First 500 Customers
*Goal: scale, team workflows, growth, more channels.*

| Item | Priority | Est. |
|---|---|---|
| Additional channels: Facebook, TikTok (OAuth + publish) | High | 6 d each |
| Team accounts + content approval flow | Medium | 5 d |
| Full mobile parity + push notifications | Medium | 8 d |
| Advanced analytics (growth, best-time-to-post) | Medium | 5 d |
| Self-serve coupons/referrals, dunning, annual plans | Medium | 4 d |
| i18n system (EN + DE switch) | Low | 3 d |
| Real-time observability, error tracking (Sentry), scaling queues | Medium | 3 d |

---

## 4. Execution Roadmap (30-Day, in Order)

Sequenced by dependency. **Day 1 actions in bold are external-dependency starters — do them first.**

**Week 1 — Foundations & Money**
1. **Submit Meta App Review prep** (needs Privacy Policy URL → do GDPR page first thing) + set up Stripe account/products with EUR prices.
2. Configure transactional email (SMTP/Resend) + German Mailables. *(unblocks reset, receipts, trial, alerts)*
3. Auth security: rate limiting + login throttle + enable Stripe webhook signature verification.
4. Password reset flow (depends on #2).
5. GDPR pages: Impressum, Datenschutzerklärung, AGB, cookie consent + account deletion + data export + Meta data-deletion callback. *(unblocks Meta review)*

**Week 2 — Payments & Trial**
6. Stripe Checkout + payment capture + activate-on-webhook (depends on #1, #3).
7. Trial enforcement, expiry job, trial-ending emails (depends on #2, #6).
8. Stripe Customer Portal link + German invoice fields (USt/sequential number).
9. Editable user settings (profile/password) + registration consent/confirm-password.

**Week 3 — The Product (AI + Setup)**
10. Business profile schema + management UI (industry, brand tone, services, location).
11. AI generation service: OpenAI client + profile-aware prompts + token metering (depends on #10).
12. AI in post composer ("✨ Mit AI generieren" → 3 options → insert).
13. Onboarding wizard tying together: business → brand → connect IG → first AI post (depends on #10, #11, #12).

**Week 4 — Make It Trustworthy & Ship**
14. Instagram live `api` driver + reconnect UX (Meta review should be approved/near-approved).
15. Publishing hardening: retries + failure notifications (email + status).
16. Error boundaries, loading/error states, fill empty states.
17. Customer support channel (contact form/WhatsApp + minimal help page).
18. Dashboard polish (trial/quota/next-post widgets).
19. **End-to-end UAT** with a friendly pilot business → soft launch to first 10.

---

### Founder's notes
- **Critical path is: Email → Stripe → AI → Onboarding.** Everything sellable hangs off those four. Parallelize GDPR/legal and Meta review since they have external lead times.
- **Don't touch mobile, multi-channel, team approvals, or i18n systems before launch.** They don't help you get the first 10 paying customers and will eat your 30 days.
- **Biggest risk to the timeline: Meta App Review.** It's outside your control — start it on Day 1 by publishing the Privacy Policy + data-deletion endpoint, or pilot with businesses where you manually assist connection.
- The scheduling/billing-schema foundation is genuinely good; you're closer to "real product" than the gaps list looks — most Phase 1 work is *connecting* existing pieces (Stripe to billing, OpenAI to prompt templates, profile to AI), not building from zero.
