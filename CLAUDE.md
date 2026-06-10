# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this project is

Klicklocal is a social media scheduling SaaS (Buffer/Hootsuite-style) for local businesses in Germany. The monorepo contains three apps that share one Laravel API:

- `backend/` — Laravel 12 API (source of truth for all business logic)
- `frontend/` — Next.js 16 customer + admin dashboard
- `mobile/` — Expo 54 React Native app

**All scheduling, billing, authorization, and social-provider logic lives in the backend.** Frontend and mobile are API clients only.

### Product domains (production)

| Role | URL |
|------|-----|
| Customer app | `https://klicklocal.app` |
| Admin dashboard | `https://admin.klicklocal.app` |
| API | `https://api.klicklocal.app` |
| Staging customer | `https://test.klicklocal.app` |
| Staging API | `https://api-test.klicklocal.app` |

Migrated from `gastrocycle.com` → `klicklocal.app` (2026).

---

## Git repositories

Frontend and backend are deployed from **separate Git repos**:

| Repo | Remote | Contents |
|------|--------|----------|
| **Frontend** | `https://github.com/emretokatli/klicklocal.git` | `frontend/` only (standalone Next.js app) |
| **API / Backend** | `https://github.com/emretokatli/klicklocal-api.git` | Monorepo root: `backend/`, `frontend/` submodule, `mobile/` submodule, `deploy/` |

When pushing changes:
1. Commit + push inside `frontend/` → `klicklocal.git`
2. Commit + push backend files in monorepo root → `klicklocal-api.git`
3. Update the `frontend` submodule pointer in `klicklocal-api` after frontend releases

---

## Production server layout (Hetzner)

**Important:** Production currently uses a **split layout**, not the monorepo path from `deploy/README.md`:

| Path | Purpose |
|------|---------|
| `/var/www/klicklocal` | **Frontend only** — Next.js root (`git pull` from `klicklocal.git`) |
| `/var/www/klicklocal-api/backend` | **Laravel API** — nginx document root: `.../backend/public` |
| systemd | `klicklocal-frontend.service` on port `3000` |
| MySQL DB | `klicklocal` |

Deploy commands:

```bash
# Frontend
cd /var/www/klicklocal
sudo -u www-data git pull
npm run build
sudo systemctl restart klicklocal-frontend

# Backend
cd /var/www/klicklocal-api
sudo -u www-data git pull
cd backend && composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache
```

Use **SSH from PowerShell** for server edits — Hetzner web console paste corrupts `$` and `_` in `.env` files.

---

## Commands

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan l5-swagger:generate   # regenerate Swagger docs
php artisan test                  # run all tests
php artisan test --filter=MyTest  # run a single test class
php artisan queue:work            # process scheduled-post jobs
php artisan migrate:status        # see pending migrations
```

Local API: `http://localhost:1981/klicklocal/backend/public/api/v1`  
Swagger UI: `http://localhost:1981/klicklocal/backend/public/api/documentation`

On Windows/XAMPP, if `php` is not on PATH:
```powershell
D:\NEWxampp\php\php.exe artisan migrate
```

### Frontend

```bash
cd frontend
npm install
cp .env.local.example .env.local
npm run dev
npm run lint
npm run build
```

Recommended local `.env.local`:
```
NEXT_PUBLIC_API_URL=/api/v1
BACKEND_API_URL=http://localhost:1981/klicklocal/backend/public/api/v1
NEXT_PUBLIC_STORAGE_URL=http://localhost:1981/klicklocal/backend/public/storage
```

> **Important:** `frontend/CLAUDE.md` (→ `AGENTS.md`) instructs you to read `node_modules/next/dist/docs/` before writing Next.js code. This version has breaking API changes.

### Mobile

```bash
cd mobile
npm install
cp .env.example .env
npm start
```

Set `EXPO_PUBLIC_API_URL` to your LAN IP when testing on a physical device.

> **Important:** `mobile/CLAUDE.md` (→ `AGENTS.md`) instructs you to check the exact Expo v56 docs before writing mobile code.

---

## Key architecture patterns

### Workspace context

Every authenticated customer request that touches workspace-scoped resources must include `X-Workspace-Id: <id>` (or `?workspace_id=<id>`). The `workspace.team` middleware (`SetWorkspaceTeam`) resolves the workspace from this header, verifies membership, and injects it as `$request->attributes->get('workspace')`. It also calls `setPermissionsTeamId($workspace->id)` so Spatie scopes permissions to that workspace, then clears it in a `finally` block.

Platform-level permissions use team ID `0` (see `TeamContext::PLATFORM`). Never call Spatie permission checks without first establishing the right team context via `AuthorizationService`.

### Authorization layers

1. `auth:sanctum` — bearer token required
2. `customer` (`EnsureCustomer`) — user must not be a platform admin
3. `platform.admin` (`EnsurePlatformAdmin`) — must be `super_admin`, `admin`, or `support`
4. `workspace.team` (`SetWorkspaceTeam`) — resolves workspace and sets Spatie team context
5. `subscription.required` (`EnsureWorkspaceSubscription`) — returns 402 if workspace has no active/trialing subscription
6. `feature.quota:{key}` (`EnsureFeatureQuota`) — enforces billing plan limits (e.g., `feature.quota:scheduled_posts_monthly`)

`subscription.required` sits inside the `workspace.team` group and gates all AI, posts, and quota endpoints. Routes that should work without a subscription (e.g., `GET /transactions`) are placed outside this inner group.

### Social provider driver pattern

Each provider resolves to either a real API class or a fake via `SocialProviderFactory`. Configure with env vars:

```
SOCIAL_INSTAGRAM_DRIVER=fake   # or 'api'
SOCIAL_FACEBOOK_DRIVER=fake
SOCIAL_LINKEDIN_DRIVER=fake
```

Fake providers simulate success/failure with `SOCIAL_FAKE_SUCCESS_RATE`, `SOCIAL_FAKE_MIN_DELAY_MS`, `SOCIAL_FAKE_MAX_DELAY_MS`. Controllers must always call providers through `SocialProviderInterface` — never call provider APIs directly.

### OpenAI driver pattern

Bound in `AppServiceProvider` via `OpenAiClientInterface`. Set `OPENAI_DRIVER=fake` (default when no key) for a deterministic local stub; set `OPENAI_DRIVER=api` + `OPENAI_API_KEY` for the real OpenAI API.

`WebsiteAnalysisService` also respects `OPENAI_DRIVER=fake` and returns German placeholder text when no API key is set.

Use a **chat model** for text (`gpt-4o`, `gpt-4o-mini`) — not image models like `gpt-image-*`.

### Billing quota top-up pattern

`QuotaAddon` model stores one-off quota purchases (`feature_key`, `amount`, `expires_at`, `workspace_id`). `FeatureQuotaService` sums plan limits + active addon amounts to determine remaining quota.

| Endpoint | Auth | Purpose |
|----------|------|---------|
| `GET /quota/packages` | workspace.team | Available top-up packages |
| `POST /quota/topup` | workspace.team | Purchase a top-up package → creates `QuotaAddon` |
| `GET /transactions` | workspace.team | Customer's billing transactions (no subscription gate) |
| `POST /posts/quick-publish` | workspace.team + subscription.required + feature.quota:scheduled_posts_monthly | Immediately publish generated content |
| `POST /admin/subscriptions/demo` | platform.admin | Grant a trialing demo subscription (`metadata.demo = true`) |

### Queue publishing

`PublishPostJob` handles all post publishing. It retries 3 times with backoff `[30, 120, 300]` seconds. Use `QUEUE_CONNECTION=sync` for immediate local testing without running a worker.

### Frontend API client

`frontend/src/services/api-client.ts` wraps Axios and:
- Auto-attaches `Authorization: Bearer <token>` (from `localStorage` via `lib/token.ts`)
- Auto-attaches `X-Workspace-Id` from stored workspace ID
- On 401, clears the token and redirects to `/login`
- Throws `ApiClientError` (with `status` and `errors` fields) for failed responses

Use the typed helpers (`apiGet<T>`, `apiPost<T>`, etc.) — they unwrap `data.data` from the `ApiSuccess<T>` envelope.

### Next.js same-origin proxy

`frontend/src/app/api/v1/[...path]/route.ts` proxies all `/api/v1/*` calls server-side to `BACKEND_API_URL`. This avoids CORS and keeps the token out of the browser's cross-origin requests. Only `Authorization`, `Content-Type`, and `X-Workspace-Id` headers are forwarded.

---

## Registration & onboarding (current flow)

Implemented 2026 — email-first registration with persisted multi-step onboarding.

### User journey

```
/register (email only)
  → POST /auth/register-email
  → /onboarding (dedicated wizard, centered UI — NOT auth/register design)
  → POST /auth/onboarding/complete (password + workspace + business profile)
  → /ai
```

### Onboarding wizard steps (`/onboarding`)

1. Get started (intro)
2. Your name
3. Business / workspace name
4. Website URL
5. KYC block: Branche, team size, revenue, customer source, social channels
6. Check website (AI analysis loading)
7. Business description (AI pre-filled, editable)
8. Target audience (AI pre-filled)
9. Unique value proposition (AI pre-filled)
10. Additional notes (optional)
11. Primary goal (what Klicklocal should solve first)
12. Set password (email shown read-only)

### Persistence & resume

- Progress stored on **`users`** table: `onboarding_step`, `onboarding_data` (JSON), `onboarding_completed_at`
- `password` is nullable until the final step
- Abandoned users resume via `/register` with same email → new token + saved step
- Login redirects to `/onboarding` if `onboarding_completed_at` is null
- Dashboard and home page blocked until onboarding complete (`OnboardingGate`, `HomeOnboardingRedirect`)

### Key backend files

| File | Role |
|------|------|
| `app/Services/Auth/UserOnboardingService.php` | register-email, save progress, complete |
| `app/Services/Ai/WebsiteAnalysisService.php` | fetch website + OpenAI JSON analysis |
| `app/Http/Controllers/Api/V1/WebsiteAnalysisController.php` | public analyze endpoint |
| `database/migrations/2026_06_08_000002_add_user_onboarding_to_users_table.php` | user onboarding columns |
| `database/migrations/2026_06_08_000001_add_onboarding_fields_to_business_profiles_table.php` | extended profile columns |

### Key frontend files

| File | Role |
|------|------|
| `src/components/auth/EmailRegisterForm.tsx` | `/register` — email only |
| `src/components/onboarding/OnboardingWizard.tsx` | main wizard |
| `src/components/onboarding/OnboardingShell.tsx` | centered layout (no auth split panel) |
| `src/components/auth/OnboardingGate.tsx` | route guards |
| `src/services/user-onboarding.service.ts` | onboarding API client |
| `src/lib/onboarding-wizard/constants.ts` | steps, KYC options, data types |

### Auth API endpoints (new)

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| POST | `/auth/register-email` | Public | Start or resume with email |
| GET | `/auth/onboarding` | Sanctum | Load saved progress |
| PATCH | `/auth/onboarding` | Sanctum | Save step + data |
| POST | `/auth/onboarding/complete` | Sanctum | Password + workspace + profile |
| POST | `/onboarding/analyze-website` | Public (throttled) | AI website analysis |

`/auth/me` now includes: `onboarding_completed`, `onboarding_step`, `onboarding_data`.

---

## KI-Studio / Reel Director Studio (`/ai`)

15-second Instagram/TikTok reel creation UI inspired by the Google AI Studio prototype (`klicklocal-reel-creator`).

### Features

- 3-column layout: copilot (tone) | 9:16 phone preview | export/timeline
- Uses existing `POST /ai/generate` with business profile context
- Maps AI output to 4-scene reel script
- No duplicate business/industry questions — reads from saved `business_profiles`
- Playback timer + simple Web Audio synth

### Key frontend files

```
frontend/src/components/ai/reel-studio/
  ReelStudio.tsx          — orchestrator
  ReelCopilotPanel.tsx    — tone + generate (profile summary read-only)
  ReelPhonePreview.tsx    — 9:16 preview with safe zones
  ReelScriptEditor.tsx    — per-scene editing
  ReelExportPanel.tsx     — caption/script export

frontend/src/lib/reel-studio/   — types, data, utils
frontend/src/hooks/use-reel-playback.ts

frontend/src/components/ai/ContentGenerationWizard.tsx
  — AI content generation + quick-publish to Instagram/TikTok
  — Uses postsService.quickPublish() → POST /posts/quick-publish
  — SubscriptionGate wraps subscription-gated features
```

Requires a complete business profile (`business_name` + `business_type`). City is optional since onboarding may not collect it.

---

## Business profile (extended schema)

`business_profiles` table fields:

| Column | Purpose |
|--------|---------|
| `business_name`, `business_type`, `city` | Core (city optional) |
| `description`, `tone_of_voice`, `products_services` | AI context |
| `website` | From onboarding |
| `team_size`, `monthly_revenue`, `customer_source` | KYC |
| `social_media_channels` | JSON array |
| `target_audience`, `unique_value_proposition`, `additional_notes` | AI/onboarding |
| `primary_goal` | User's first priority |

`BusinessProfile::isComplete()` requires only `business_name` + `business_type`.

---

## Social account integrations

### Instagram

- OAuth connect/disconnect/status on `/social-accounts`
- Routes: `/social-accounts/instagram/connect|disconnect|status|callback`
- Config: `INSTAGRAM_APP_ID`, `INSTAGRAM_APP_SECRET`, `INSTAGRAM_REDIRECT_URI`, `FRONTEND_URL`

### TikTok (added 2026)

- Backend: `TikTokSocialAccountController`, `app/Services/SocialProviders/TikTok/`
- Frontend: TikTok card on `/social-accounts` via `SocialProviderConnectionCard`
- Config: `backend/config/tiktok.php`
- Routes: `/social-accounts/tiktok/connect|disconnect|status|callback`

**Env vars:**
```
TIKTOK_ENABLED=true
TIKTOK_CLIENT_KEY=
TIKTOK_CLIENT_SECRET=
TIKTOK_REDIRECT_URI=https://api.klicklocal.app/api/v1/social-accounts/tiktok/callback
TIKTOK_FRONTEND_REDIRECT=https://klicklocal.app/social-accounts
```

**Known issue:** Backend requests scopes `user.info.basic`, `video.publish`, `video.upload`. If TikTok Developer Portal has not approved Content Posting API, OAuth fails with **"scope"** error. Fix options:
- Quick: reduce scopes to `user.info.basic` only until API is approved
- Full: enable Content Posting API + all scopes in TikTok Developer Portal

Production backend must be updated separately from frontend (`git pull` in `/var/www/klicklocal-api`).

---

## API structure

All backend APIs are under `/api/v1`. Route groups:

- **Public:** `auth/register`, `auth/register-email`, `auth/login`, `onboarding/analyze-website`, social OAuth callbacks, `webhooks/stripe`
- **Authenticated customer** (`auth:sanctum` + `customer`): workspaces, auth/onboarding, posts, media, billing, subscription, usage, invoices, social accounts
- **Workspace-scoped** (above + `workspace.team`): business-profile, workspace onboarding, posts, media, billing, AI generate, social accounts, transactions, quota/packages, quota/topup
- **Subscription-gated** (above + `subscription.required`): AI generate, posts, posts/quick-publish, quota/topup
- **Admin** (`auth:sanctum` + `platform.admin`): `/admin/*` — users, plans, subscriptions, coupons, settings, ai-prompts, usage, providers, quota-addons, subscriptions/demo

---

## Frontend routes (customer)

| Route | Purpose |
|-------|---------|
| `/` | Marketing landing (redirects incomplete onboarding) |
| `/register` | Email-only registration |
| `/login` | Sign in |
| `/onboarding` | Multi-step onboarding wizard |
| `/dashboard` | Main dashboard (requires completed onboarding) |
| `/ai` | KI-Studio / Reel Director Studio |
| `/social-accounts` | Instagram + TikTok connect |
| `/posts`, `/calendar`, `/media` | Content management |
| `/settings`, `/billing`, `/usage` | Account |
| `/invoices` | Billing invoices list |
| `/transactions` | Billing transactions list |
| `/agb`, `/datenschutz`, `/impressum`, `/widerruf` | Legal pages (German) |

Admin routes under `/admin/*` on `admin.klicklocal.app`.

---

## Database migrations (recent)

Run with `php artisan migrate` (local) or `php artisan migrate --force` (production).

| Migration | Changes |
|-----------|---------|
| `2026_06_08_000001_add_onboarding_fields_to_business_profiles_table` | KYC + website + goals on `business_profiles` |
| `2026_06_08_000002_add_user_onboarding_to_users_table` | `onboarding_step`, `onboarding_data`, `onboarding_completed_at`; nullable `password`; backfills existing users as complete |

Earlier core tables: `users`, `workspaces`, `workspace_members`, `business_profiles`, `ai_generations`, `posts`, `media`, `social_accounts`, billing/subscription tables.

---

## Deployment environments

| Environment | Branch | API |
|-------------|--------|-----|
| Production | `main` | `https://api.klicklocal.app/api/v1` |
| Staging | `develop` | `https://api-test.klicklocal.app/api/v1` |

- Production and staging use separate databases (`klicklocal_prod` / `klicklocal_staging` per `deploy/README.md`; production Hetzner currently uses DB name `klicklocal`).
- Never hardcode localhost URLs in deployed code — all endpoints come from env vars.
- Server runbook: `deploy/README.md`; staging overview: `docs/UAT.md`; frontend (Vercel): `docs/VERCEL-DEPLOY.md`

### Production `.env` essentials

**Backend** (`/var/www/klicklocal-api/backend/.env`):
- `APP_URL=https://api.klicklocal.app`
- `FRONTEND_URL=https://klicklocal.app`
- `SANCTUM_STATEFUL_DOMAINS` — include production domains
- `OPENAI_DRIVER=api`, `OPENAI_API_KEY`, `OPENAI_MODEL=gpt-4o` (for real AI)
- Social provider keys (Instagram, TikTok)
- Retype `.env` lines manually if Laravel dotenv parse errors occur (invisible chars from paste)

**Frontend** (`/var/www/klicklocal/.env.local`):
- `NEXT_PUBLIC_API_URL=/api/v1` (proxy mode — leave unchanged)
- `BACKEND_API_URL=https://api.klicklocal.app/api/v1`

---

## i18n

All customer-facing UI copy is German in `frontend/src/lib/i18n/de.ts`. Key sections:
- `de.registerWizard.*` — onboarding wizard strings
- `de.ai.reelStudio.*` — Reel Director Studio
- `de.ai.wizard.*` — AI content generation wizard (share/quick-publish strings use function form: `de.ai.wizard.shareOn('instagram')`)
- `de.socialAccounts.*` — Instagram + TikTok labels
- `de.business.*` — business profile form
- `de.billing.*` — billing page, transactions table (`transactionDate`, `transactionDesc`, `transactionProvider`, `noTransactions`)
- `de.nav.transactions` — sidebar nav entry for `/transactions`

---

## Common pitfalls

1. **422 on `/onboarding/analyze-website`** — no `OPENAI_API_KEY` or wrong model; fake driver returns placeholders locally.
2. **TikTok OAuth "scope" error** — scopes not approved in TikTok Developer Portal.
3. **502 after frontend deploy** — TypeScript build error; always run `npm run build` before restart.
4. **Backend routes 404 after deploy** — run `php artisan route:cache` in backend directory.
5. **Onboarding null crash** — `onboarding_data` fields may be `null` from DB; use `mergeOnboardingData()` which coerces nulls to empty strings.
6. **Git on server** — use `sudo -u www-data git pull` to avoid dubious ownership errors.
7. **PowerShell paths** — quote paths with parentheses: `"src/app/(dashboard)/ai/page.tsx"`.
8. **Two git repos** — frontend changes go to `klicklocal.git`; backend to `klicklocal-api.git`.

---

## Related docs

| File | Contents |
|------|----------|
| `README.md` | Quick start |
| `PROJECT.md` | Full architecture and roadmap |
| `deploy/README.md` | Server deployment runbook |
| `docs/UAT.md` | Staging environment |
| `docs/FIRST-POST-MVP.md` | What was built in MVP phase |
| `frontend/CLAUDE.md` | Next.js-specific agent instructions |
