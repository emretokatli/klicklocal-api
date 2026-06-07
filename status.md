# Klicklocal — Project Status

**Last updated:** 2026-06-06
**Goal:** Launch to the first 10 paying customers (German local businesses) within 30 days.
**Companion docs:** `Prompt1.md` (MVP Gap Analysis & Roadmap) · `Prompt2.md` (Customer Journey Screen Audit) · `docs/FIRST-POST-MVP.md` (first-post loop build notes)

Legend: ✅ Done · 🟡 Partial / needs rework · ❌ Missing · 🔴 Launch blocker

---

## 1. Headline Status

The product now has a working **core value loop**: sign up → describe business → upload photo → generate AI content → schedule post. This was the single biggest gap in the original analysis and is now **built** (see `docs/FIRST-POST-MVP.md`).

**Still cannot launch** because three blockers remain:

1. 🔴 **No real payments** — no Stripe Checkout / card capture; Stripe SDK not installed; webhook verification stubbed. Cannot collect a euro.
2. 🔴 **Not legally sellable in Germany** — no Impressum, Datenschutzerklärung, AGB, or cookie consent.
3. 🔴 **No transactional email** — `MAIL_MAILER=log`; blocks password reset, receipts, trial reminders, publish alerts.

---

## 2. What Changed Since the Gap Analysis (now DONE)

| Item | Prior status | Now | Evidence |
|---|---|---|---|
| AI content generation | ❌ Missing | ✅ Done | `POST /ai/generate`, `AiContentGenerationService`, `OpenAiClient` + `FakeOpenAiClient` |
| Business profile / setup | ❌ Missing | ✅ Done | `business_profiles` table, `BusinessProfileController`, `BusinessProfileForm.tsx` |
| Onboarding wizard | ❌ Missing | ✅ Done | 4-step `OnboardingStepper.tsx`, `/onboarding`, `workspaces.onboarding_*` |
| AI generation history | ❌ Missing | ✅ Done | `ai_generations` table, `GET /ai/generations`, KI-Studio page |
| Generate → draft post → schedule | ❌ Missing | ✅ Done | reuses `POST /posts` + `POST /posts/{id}/schedule` |
| OpenAI wiring | ❌ Missing | ✅ Done | `config/services.php` + `AppServiceProvider` binding (driver: fake/api) |

---

## 3. Current State by Area

| Area | Status | Notes |
|---|---|---|
| Register / Login / Logout | ✅ | Sanctum tokens |
| AI content generation | ✅ | OpenAI + fake driver; structured caption/story/hashtags/CTA |
| Business profile | ✅ | 1:1 with workspace; feeds AI |
| Onboarding wizard | ✅ | 4 steps, register → /onboarding |
| Posts / scheduling / publishing | ✅ | CRUD + queue jobs |
| Media upload | ✅ | Used in AI + posts |
| Admin panel | ✅ | 10 pages |
| German web UI + EUR | ✅ | Hardcoded `de.ts`; no i18n system |
| Social connection (Instagram) | 🟡 | OAuth real, defaults to `fake` driver; needs live + Meta App Review |
| Subscription / Trial | 🟡 | Local records only; trial **never enforced/expired** |
| Dashboard analytics | 🟡 | Post-status counts only |
| Calendar | 🟡 | Placeholder |
| User settings | 🟡 | Read-only; no edit profile/password, no delete |
| Error handling | 🟡 | Good empty states; no error boundaries; spotty loading/error |
| Mobile app | 🟡 | Auth + workspaces only; English-only |
| Billing / Payment | 🔴 ❌ | No Stripe Checkout, no card capture, SDK not installed |
| Transactional email | 🔴 ❌ | `MAIL_MAILER=log`, no Mailables |
| Password reset | 🔴 ❌ | Table exists, flow missing |
| GDPR / legal pages | 🔴 ❌ | No Impressum/Datenschutz/AGB/cookie consent |
| Account deletion + data export | 🔴 ❌ | GDPR Art. 17/20 |
| Security (auth throttling, webhook verify) | 🔴 🟡 | No rate limiting on auth; Stripe webhook verify stubbed |
| Email verification | ❌ | Column exists, unused |
| Notifications | ❌ | None (in-app/email/push) |
| Customer support channel | ❌ | No in-app contact/help |
| Content approval | ❌ | Deferred (Phase 3) |

---

## 4. Launch Blockers — Remaining (Phase 1)

🔴 = hard blocker. Ordered by recommended execution.

- [ ] 🔴 Transactional email (SMTP/Resend) + German Mailables — *enabler for reset/receipts/trial/alerts*
- [ ] 🔴 Stripe Checkout + real payments + webhook signature verification
- [ ] 🔴 Trial enforcement + expiry job + trial-ending reminders
- [ ] 🔴 Password reset (forgot + reset screens)
- [ ] 🔴 GDPR: Impressum, Datenschutz, AGB, cookie consent banner
- [ ] 🔴 Account deletion + data export
- [ ] 🔴 Auth rate limiting + register consent checkboxes (AGB/Datenschutz)
- [ ] 🔴 Instagram live `api` driver + submit Meta App Review *(start Day 1 — external lead time)*
- [ ] High: Editable profile + change password
- [ ] High: Publishing hardening (retries + failure notifications)
- [ ] High: Customer support channel (contact + help)
- [ ] High: Global error boundary + loading/error states

**Estimated remaining Phase 1 effort:** ~20–22 dev-days (down from ~33; AI/profile/onboarding now complete).

---

## 5. Post-Launch (reference)

- **Phase 2 (first 50):** functional calendar, email verification, in-app notifications, basic IG analytics, Stripe Customer Portal + German invoice PDFs (USt), AI improvements, mobile posts/AI/connect, help center.
- **Phase 3 (first 500):** Facebook + TikTok channels, team accounts + approvals, full mobile + push, advanced analytics, coupons/referrals/dunning, i18n system, observability (Sentry).

Full detail: see `Prompt1.md` and `Prompt2.md`.

---

## 6. Config Notes

- `OPENAI_DRIVER=fake` by default (works with no key). Set `OPENAI_API_KEY` to enable real generation (`api` driver auto-enables).
- For image-aware generation in production, uploaded media URL must be publicly reachable by OpenAI (`APP_URL` / storage must be public).
- Backend tests: 27 passing (no regressions reported after first-post-MVP build).

---

## 7. Critical Path to Launch

**Email → Stripe → Trial enforcement → GDPR/Legal → Meta App Review.**
The product (AI loop) is done; remaining work is *getting paid*, *being legal in Germany*, and *not locking users out*. Start Meta App Review and Stripe setup on Day 1 — both have external lead times.
