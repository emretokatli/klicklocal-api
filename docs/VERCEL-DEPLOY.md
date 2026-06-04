# Deploy frontend to Vercel

Repository: [github.com/emretokatli/klicklocal](https://github.com/emretokatli/klicklocal)  
Backend (UAT): `https://gastrocycle.com/public/api/v1`

## 1. Import project in Vercel

1. [vercel.com](https://vercel.com) → **Add New Project**
2. Import **emretokatli/klicklocal**
3. Framework: **Next.js** (auto-detected)
4. Root directory: **`.`** (repo root is already the Next app)

## 2. Environment variables

In **Project → Settings → Environment Variables**, add for **Production** (and Preview if you want):

### Recommended (proxy — no CORS setup)

| Name | Value | Required |
|------|--------|----------|
| `NEXT_PUBLIC_API_URL` | `/api/v1` | Yes |
| `BACKEND_API_URL` | `https://gastrocycle.com/public/api/v1` | **Yes** — without this, login fails with `ECONNREFUSED 127.0.0.1:1981` |
| `NEXT_PUBLIC_STORAGE_URL` | `https://gastrocycle.com/public/storage` | Yes |

The app calls `/api/v1` on your Vercel domain; Next.js forwards to Laravel (`src/app/api/v1/[...path]/route.ts`).

> **Login 500?** Vercel logs show `connect ECONNREFUSED 127.0.0.1:1981` → add `BACKEND_API_URL` above and redeploy.

### Alternative (direct API — your request)

| Name | Value |
|------|--------|
| `NEXT_PUBLIC_API_URL` | `https://gastrocycle.com/public/api/v1` |
| `NEXT_PUBLIC_STORAGE_URL` | `https://gastrocycle.com/public/storage` |

Also add your Vercel URL to the **backend** `.env` on Strato:

```env
CORS_ALLOWED_ORIGINS=https://gastrocycle.com,https://www.gastrocycle.com,https://YOUR-APP.vercel.app
```

Then on the server: `php artisan config:clear`

(`*.vercel.app` is already allowed via `config/cors.php` patterns after you deploy the latest backend.)

## 3. Deploy

Click **Deploy**. Build command: `npm run build` (default).

## 4. Verify

1. Open your Vercel URL → `/login`
2. Sign in: `admin@klicklocal.test` / `password`
3. Dashboard loads workspaces / billing

If login fails with a network/CORS error, switch to the **proxy** env vars (table above).

## 5. Custom domain (optional)

Vercel → **Domains** → add e.g. `app.gastrocycle.com`  
Add that origin to backend `CORS_ALLOWED_ORIGINS` if using direct API mode.

## Local reference

See `frontend/.env.production.example` and `frontend/.env.uat.example`.
