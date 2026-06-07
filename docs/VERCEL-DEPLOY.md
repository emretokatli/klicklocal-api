# Deploy frontend to Vercel

Repository: [github.com/emretokatli/klicklocal](https://github.com/emretokatli/klicklocal)

| Environment | Branch | Frontend | API |
|-------------|--------|----------|-----|
| Production | `main` | `https://klicklocal.app` / `https://admin.klicklocal.app` | `https://api.klicklocal.app/api/v1` |
| Staging | `develop` | `https://test.klicklocal.app` / `https://admin-test.klicklocal.app` | `https://api-test.klicklocal.app/api/v1` |

> Self-hosting on the Ubuntu server instead? Use [`deploy/README.md`](../deploy/README.md).

## 1. Import project in Vercel

1. [vercel.com](https://vercel.com) → **Add New Project**
2. Import **emretokatli/klicklocal**
3. Framework: **Next.js** (auto-detected)
4. Root directory: **`frontend`**
5. **Production Branch:** `main`. Add `develop` as a tracked branch for staging deployments.

## 2. Environment variables

In **Project → Settings → Environment Variables**, scope values per Vercel
environment. Never hardcode endpoints in code — they all come from these vars.

### Production (scope: Production, git branch `main`)

| Name | Value |
|------|--------|
| `NEXT_PUBLIC_API_URL` | `/api/v1` |
| `BACKEND_API_URL` | `https://api.klicklocal.app/api/v1` |
| `NEXT_PUBLIC_STORAGE_URL` | `https://api.klicklocal.app/storage` |

### Staging (scope: Preview, git branch `develop`)

| Name | Value |
|------|--------|
| `NEXT_PUBLIC_API_URL` | `/api/v1` |
| `BACKEND_API_URL` | `https://api-test.klicklocal.app/api/v1` |
| `NEXT_PUBLIC_STORAGE_URL` | `https://api-test.klicklocal.app/storage` |

The app calls `/api/v1` on its own Vercel domain; Next.js forwards server-side to
Laravel (`src/app/api/v1/[...path]/route.ts`), so there is no browser CORS.

> **Login 502 / "Cannot reach the API server"?** `BACKEND_API_URL` is missing or
> wrong for that environment — set it (table above) and redeploy.

### Alternative (browser calls Laravel directly)

| Name | Production value |
|------|------------------|
| `NEXT_PUBLIC_API_URL` | `https://api.klicklocal.app/api/v1` |
| `NEXT_PUBLIC_STORAGE_URL` | `https://api.klicklocal.app/storage` |

`*.klicklocal.app` is already allowed via `backend/config/cors.php` patterns.

## 3. Custom domains

In Vercel → **Domains**:

- Production project/branch `main` → `klicklocal.app`, `admin.klicklocal.app`
- Staging (preview) branch `develop` → `test.klicklocal.app`, `admin-test.klicklocal.app`

## 4. Verify

1. Open the domain → `/login`
2. Sign in with your seeded admin
3. Dashboard loads workspaces

## Local reference

See `frontend/.env.production.example` (production) and `frontend/.env.uat.example` (staging).
