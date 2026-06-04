# Deploy Laravel backend on IONOS / webspace (UAT)

You uploaded the backend into a folder on webspace and created MySQL. Follow these steps in order.

## 1. Folder layout on the server

Upload the **entire** `backend/` project (same structure as locally):

```
your-folder/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/          ← web traffic must enter HERE
│   ├── index.php
│   └── .htaccess
├── routes/
├── storage/
├── vendor/          ← required (see step 3)
├── .env             ← create on server (step 4)
├── artisan
└── composer.json
```

**Important:** Browsers must only reach the **`public`** directory, not `app/`, `.env`, or `vendor/`.

### Option A — recommended: point document root to `public`

In IONOS / webspace control panel:

- Domain or subdomain → **Document root** →  
  `.../your-folder/public`

Then your API base URL is:

```text
https://your-domain.com/api/v1
```

Health check:

```text
https://your-domain.com/up
```

### Option B — IONOS “Intern” redirect to `/klicklocal` (your setup)

In the IONOS panel, **Stammverzeichnis = `/klicklocal`**. The domain root **is already** that folder — do **not** put `/klicklocal` in the browser URL.

| Wrong (404) | Correct |
|-------------|---------|
| `https://gastrocycle.com/klicklocal/public/ping.html` | `https://gastrocycle.com/public/ping.html` |
| `https://gastrocycle.com/klicklocal/public/up` | `https://gastrocycle.com/public/up` |
| `APP_URL=.../klicklocal/public` | `APP_URL=https://gastrocycle.com/public` |

1. **`public/.htaccess`**: `RewriteBase /public/`
2. **Server `.env`**: `APP_URL=https://gastrocycle.com/public`
3. **Optional (best):** In IONOS, set Stammverzeichnis to **`/klicklocal/public`** → then URLs become `https://gastrocycle.com/up` and `RewriteBase /` (use `public/.htaccess.docroot`).

---

## 2. Database (phpMyAdmin)

1. Open phpMyAdmin for database **`dbs15734238`**.
2. Import **`backend/database/sql/klicklocal_uat_schema.sql`** (full script, once).
3. Confirm tables exist (`users`, `plans`, `permissions`, …).

---

## 3. Install PHP dependencies (`vendor`)

Laravel **cannot run** without `vendor/`.

**On your PC** (before or after upload):

```powershell
cd d:\NEWxampp\htdocs\klicklocal\backend
D:\NEWxampp\php\php.exe C:\path\to\composer.phar install --no-dev --optimize-autoloader
```

Then upload the **`vendor`** folder to the server (FTP / file manager).

If the host offers **SSH** and Composer:

```bash
cd /path/to/your-folder
composer install --no-dev --optimize-autoloader
```

Requirements: **PHP 8.2+**, extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `json`, `fileinfo`, `ctype`.

---

## 4. Create `.env` on the server

On the server, in the backend root (next to `artisan`), create **`.env`** — copy from your local **`backend/.env.uat`** and adjust:

```env
APP_NAME="Klicklocal"
APP_ENV=uat
APP_KEY=base64:xxxxxxxx   # see step 5
APP_DEBUG=false
APP_URL=https://YOUR-REAL-DOMAIN/path/to/public

DB_CONNECTION=mysql
DB_HOST=database-5020578088.webspace-host.com
DB_PORT=3306
DB_DATABASE=dbs15734238
DB_USERNAME=dbu1020849
DB_PASSWORD=your_password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

SANCTUM_STATEFUL_DOMAINS=your-frontend-domain.com,localhost:3000
CORS_ALLOWED_ORIGINS=https://your-frontend-domain.com,http://localhost:3000

L5_SWAGGER_CONST_HOST=https://YOUR-REAL-DOMAIN/path/to/public/api/v1
```

Replace:

| Placeholder | Example |
|-------------|---------|
| `APP_URL` | `https://api.mysite.de/public` or `https://mysite.de/klicklocal/public` |
| `YOUR-REAL-DOMAIN` | Hostname only, no path |
| Frontend domains | Where Next.js / Expo will run |

**Do not** leave `YOUR_UAT_DOMAIN` — Laravel uses `APP_URL` for links and Sanctum.

---

## 5. Laravel setup commands (SSH or local upload)

Run once in the backend folder on the server (SSH). If you have no SSH, run on PC with `.env` pointing at UAT DB only if remote MySQL allows your IP (often it does **not** on IONOS).

```bash
php artisan key:generate          # only if APP_KEY is empty
php artisan config:clear
php artisan storage:link          # public/storage → storage/app/public
php artisan db:seed               # roles, plans, admin user
php artisan config:cache
php artisan route:cache
```

Default admin after seed:

- Email: `admin@klicklocal.test`
- Password: `password`

---

## 6. File permissions

Make these writable by the web server user:

```text
storage/
bootstrap/cache/
```

On FTP: **755** or **775** on folders; apply recursively to `storage` and `bootstrap/cache`.

If you get **500 errors**, check `storage/logs/laravel.log` (via FTP download).

---

## 7. Verify deployment

| Test | Expected |
|------|----------|
| `GET https://your-domain/.../public/up` | JSON / “healthy” |
| `GET https://your-domain/.../public/` | JSON with API name + documentation link |
| `POST .../public/api/v1/auth/login` | JSON with token (after seed) |

Login test (curl):

```bash
curl -X POST "https://YOUR-DOMAIN/path/to/public/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"email\":\"admin@klicklocal.test\",\"password\":\"password\"}"
```

Swagger (optional):

```text
https://your-domain/.../public/api/documentation
```

---

## 8. Connect the Next.js frontend

In `frontend/.env.local` (or `.env.uat.local`):

```env
NEXT_PUBLIC_API_URL=https://YOUR-DOMAIN/path/to/public/api/v1
NEXT_PUBLIC_STORAGE_URL=https://YOUR-DOMAIN/path/to/public/storage
```

Rebuild/redeploy the frontend after changing env vars.

---

## 9. Queue worker (scheduled posts)

Shared hosting often **cannot** run long-lived `queue:work`. Options:

- IONOS **cron** every minute:  
  `php /path/to/artisan schedule:run`  
  and/or  
  `php /path/to/artisan queue:work --stop-when-empty`
- Or use a separate VPS worker later.

For basic API + login + billing UI, queues are not required immediately.

---

## 10. Checklist

- [ ] Document root = **`public`** (or correct subfolder URL in `APP_URL`)
- [ ] **`vendor/`** uploaded
- [ ] **`.env`** on server with DB + `APP_URL` + `APP_KEY`
- [ ] SQL schema imported
- [ ] `storage` + `bootstrap/cache` writable
- [ ] `php artisan storage:link` + `db:seed` (SSH)
- [ ] `/up` works
- [ ] Login API works
- [ ] `CORS_ALLOWED_ORIGINS` includes your frontend URL

---

## 500 Internal Server Error

**Even `ping.php` returns 500?** → See **[UAT-500-TROUBLESHOOTING.md](UAT-500-TROUBLESHOOTING.md)** (PHP disabled, parent `.htaccess`, IONOS PHP version).

1. **Delete or empty** `klicklocal/.htaccess` on the server (rewrite there causes `public/public` → 500).
2. **RewriteBase** in `public/.htaccess`:
   - URL contains `/klicklocal/public/` → keep `RewriteBase /klicklocal/public/`
   - Document root is `public` (URL is `gastrocycle.com/up`) → **comment out** `RewriteBase` or use `public/.htaccess.docroot`
3. Upload **`public/uat-check.php`** and open  
   `https://gastrocycle.com/klicklocal/public/uat-check.php` — shows PHP version, missing `vendor`, permissions.
4. In hosting panel: **PHP 8.2+** for the domain.
5. `storage/` and `bootstrap/cache/` → chmod **775**.

## Common errors

| Symptom | Fix |
|---------|-----|
| 500 / blank page | See section above; `storage/logs/laravel.log`; missing `vendor` |
| 404 on `/api/v1/...` | Wrong `APP_URL` or document root not `public`; enable `mod_rewrite` |
| DB connection error | Use host `database-5020578088.webspace-host.com`, not `localhost` |
| CORS error in browser | Add frontend URL to `CORS_ALLOWED_ORIGINS` in `.env`, then `config:clear` |
| 401 on API | Use `Authorization: Bearer <token>` header |

---

## What you should **not** upload

- `.env` from local machine with `127.0.0.1` DB (create UAT `.env` on server)
- `node_modules/`, `tests/` (optional to skip)
- `.git/` (optional)

Always keep **`.env` out of public HTTP** — it must stay in the parent of `public/`, not inside `public/`.
