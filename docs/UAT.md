# Staging environment

> Staging replaces the old "UAT" environment. It deploys from the **`develop`**
> branch and is fully isolated from production.

| Item | Value |
|------|-------|
| Customer app | `https://test.klicklocal.app` |
| Admin | `https://admin-test.klicklocal.app` |
| API | `https://api-test.klicklocal.app` |
| Branch | `develop` |
| Database | `klicklocal_staging` (isolated from production) |

Full server setup: **[../deploy/README.md](../deploy/README.md)**.

Env templates:

- Backend: `backend/.env.uat.example` (and `deploy/env/backend.env.staging.example`)
- Frontend: `frontend/.env.uat.example` (and `deploy/env/frontend.env.staging.example`)
- Mobile: `mobile/.env.uat.example`

## Run staging config locally (against the staging API)

**Frontend** (proxy to the staging API):

```powershell
cd frontend
copy .env.uat.local .env.local
npm run dev
```

**Backend** — point a local Laravel at the staging database only if the staging
DB allows your IP (the dedicated server normally does not). Prefer running
backend changes on the staging server itself via the runbook.

```powershell
cd backend
copy .env.uat .env
php artisan config:clear
```

## Switch back to local dev

```powershell
cd backend
copy .env.example .env   # restore local DB_* + APP_URL, then: php artisan config:clear

cd ..\frontend
copy .env.local.example .env.local
```

## Rules

- Production and staging databases stay **isolated** — never point staging `.env`
  at the production database.
- No localhost URLs in deployed envs; all endpoints come from environment variables.
- Do not commit `.env.uat` / `.env.uat.local` with real passwords.
