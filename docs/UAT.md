# UAT environment

**Deploying on webspace?** → **[UAT-WEBSPACE-DEPLOY.md](UAT-WEBSPACE-DEPLOY.md)** (document root, `.env`, artisan, checks).

Remote MySQL (IONOS webspace-host, MySQL 8.0):

| Setting  | Value |
|----------|--------|
| Host     | `database-5020578088.webspace-host.com` |
| Database | `dbs15734238` |
| User     | `dbu1020849` |
| Port     | `3306` |

Credentials live in **`backend/.env.uat`** (gitignored). Template: **`backend/.env.uat.example`**.

## 1. Database tables

Import once in phpMyAdmin (database `dbs15734238`):

`backend/database/sql/klicklocal_uat_schema.sql`

## 2. Use UAT config locally

**Backend** (talks to remote UAT DB):

```powershell
cd backend
copy .env.uat .env
php artisan config:clear
php artisan db:seed
```

**Frontend** (after you set your public UAT hostname):

```powershell
cd frontend
copy .env.uat.local .env.local
# Edit .env.local — replace YOUR_UAT_DOMAIN with e.g. uat.example.com
npm run dev
```

## 3. Replace placeholders

In `backend/.env.uat` and `frontend/.env.uat.local`, set:

- UAT domain: **`gastrocycle.com`** (see `backend/.env.uat` and `frontend/.env.uat.local`)
- `APP_URL`, `L5_SWAGGER_CONST_HOST`, `SANCTUM_STATEFUL_DOMAINS` in backend
- `NEXT_PUBLIC_API_URL` in frontend

## 4. Switch back to local dev

```powershell
cd backend
copy .env.example .env
# restore local DB_* and run php artisan config:clear

cd ..\frontend
copy .env.local.example .env.local
```

## Security

- Do not commit `.env.uat` or `.env.uat.local` with real passwords.
- Rotate the DB password if it was shared in chat.
