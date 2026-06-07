# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this project is

Klicklocal is a social media scheduling SaaS (Buffer/Hootsuite-style). The monorepo contains three apps that share one Laravel API:

- `backend/` — Laravel 12 API (source of truth for all business logic)
- `frontend/` — Next.js 16 customer + admin dashboard
- `mobile/` — Expo 54 React Native app

**All scheduling, billing, authorization, and social-provider logic lives in the backend.** Frontend and mobile are API clients only.

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
```

Local API: `http://localhost:1981/klicklocal/backend/public/api/v1`
Swagger UI: `http://localhost:1981/klicklocal/backend/public/api/documentation`

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

## Key architecture patterns

### Workspace context

Every authenticated customer request that touches workspace-scoped resources must include `X-Workspace-Id: <id>` (or `?workspace_id=<id>`). The `workspace.team` middleware (`SetWorkspaceTeam`) resolves the workspace from this header, verifies membership, and injects it as `$request->attributes->get('workspace')`. It also calls `setPermissionsTeamId($workspace->id)` so Spatie scopes permissions to that workspace, then clears it in a `finally` block.

Platform-level permissions use team ID `0` (see `TeamContext::PLATFORM`). Never call Spatie permission checks without first establishing the right team context via `AuthorizationService`.

### Authorization layers

1. `auth:sanctum` — bearer token required
2. `customer` (`EnsureCustomer`) — user must not be a platform admin  
3. `platform.admin` (`EnsurePlatformAdmin`) — must be `super_admin`, `admin`, or `support`
4. `workspace.team` (`SetWorkspaceTeam`) — resolves workspace and sets Spatie team context
5. `feature.quota:{key}` (`EnsureFeatureQuota`) — enforces billing plan limits (e.g., `feature.quota:scheduled_posts_monthly`)

### Social provider driver pattern

Each provider resolves to either a real API class or a fake via `SocialProviderFactory`. Configure with env vars:

```
SOCIAL_INSTAGRAM_DRIVER=fake   # or 'api'
SOCIAL_FACEBOOK_DRIVER=fake
SOCIAL_LINKEDIN_DRIVER=fake
```

Fake providers simulate success/failure with `SOCIAL_FAKE_SUCCESS_RATE`, `SOCIAL_FAKE_MIN_DELAY_MS`, `SOCIAL_FAKE_MAX_DELAY_MS`. Controllers must always call providers through `SocialProviderInterface` — never call provider APIs directly.

### OpenAI driver pattern

Bound in `AppServiceProvider` via `OpenAiClientInterface`. Set `OPENAI_DRIVER=fake` (default) for a deterministic local stub; set `OPENAI_DRIVER=api` + `OPENAI_API_KEY` for the real OpenAI API.

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

## API structure

All backend APIs are under `/api/v1`. Route groups:
- Public: `auth/register`, `auth/login`, `social-accounts/instagram/callback`, `webhooks/stripe`
- Authenticated customer (`auth:sanctum` + `customer`): workspaces, posts, media, billing, subscription, usage, invoices, social accounts
- Workspace-scoped (above + `workspace.team`): posts, media, billing, social accounts
- Admin (`auth:sanctum` + `platform.admin`): `/admin/*` — users, plans, subscriptions, coupons, settings, ai-prompts, usage, providers

## Deployment environments

| Environment | Branch | API |
|-------------|--------|-----|
| Production | `main` | `https://api.klicklocal.app/api/v1` |
| Staging | `develop` | `https://api-test.klicklocal.app/api/v1` |

- Production and staging use separate databases (`klicklocal_prod` / `klicklocal_staging`).
- Never hardcode localhost URLs in deployed code — all endpoints come from env vars.
- Server runbook: `deploy/README.md`; frontend (Vercel): `docs/VERCEL-DEPLOY.md`
