# Klicklocal — Deployment Strategy & Runbook

Klicklocal runs in two isolated environments on a single Ubuntu server. Each
environment has its own app directory, its own database, and its own subdomains.

## Environments

| Environment | Branch | Customer app | Admin | API |
|-------------|--------|--------------|-------|-----|
| **Production** | `main` | `https://klicklocal.app` | `https://admin.klicklocal.app` | `https://api.klicklocal.app` |
| **Staging** | `develop` | `https://test.klicklocal.app` | `https://admin-test.klicklocal.app` | `https://api-test.klicklocal.app` |

The customer app and the admin dashboard are the **same Next.js app** served on
two subdomains. Each environment runs one Next.js process and one Laravel API.

## Rules

- **Never use localhost URLs in production/staging code or config.** Localhost is
  for local development only (see `backend/.env.example`, `frontend/.env.local.example`).
- **All API endpoints come from environment variables** (`BACKEND_API_URL`,
  `NEXT_PUBLIC_API_URL`, `NEXT_PUBLIC_STORAGE_URL`, `APP_URL`, `EXPO_PUBLIC_API_URL`).
- **Production and staging databases must stay isolated** — separate MySQL
  databases (`klicklocal_prod` vs `klicklocal_staging`) and separate users.
- **Production deploys from `main`.** **Staging deploys from `develop`.**

## Layout on the server

| Path | Environment | Ports |
|------|-------------|-------|
| `/var/www/klicklocal` | Production (`main`) | Next.js `127.0.0.1:3000` |
| `/var/www/klicklocal-staging` | Staging (`develop`) | Next.js `127.0.0.1:3001` |

Config templates in this `deploy/` folder:

| File | Purpose |
|------|---------|
| `env/backend.env.production.example` / `env/backend.env.staging.example` | Laravel `.env` |
| `env/frontend.env.production.example` / `env/frontend.env.staging.example` | Next.js `.env.local` |
| `nginx/*.klicklocal.app.conf` | One vhost per subdomain (6 total) |
| `systemd/klicklocal-frontend*.service` | Next.js process (prod + staging) |
| `supervisor/klicklocal-worker*.conf` | Queue worker (prod + staging) |

---

## 1. Server prerequisites (once)

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd \
  git unzip curl supervisor composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo bash -
sudo apt install -y nodejs
ls /run/php/   # confirm the PHP-FPM socket (e.g. php8.3-fpm.sock)
```

Point DNS A records for all six names to the server IP before requesting SSL.

## 2. Databases (isolated)

```sql
-- Production
CREATE DATABASE klicklocal_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'klicklocal_prod'@'localhost' IDENTIFIED BY 'STRONG_PROD_PASSWORD';
GRANT ALL PRIVILEGES ON klicklocal_prod.* TO 'klicklocal_prod'@'localhost';

-- Staging (separate DB + user — never share with production)
CREATE DATABASE klicklocal_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'klicklocal_staging'@'localhost' IDENTIFIED BY 'STRONG_STAGING_PASSWORD';
GRANT ALL PRIVILEGES ON klicklocal_staging.* TO 'klicklocal_staging'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Clone both environments

```bash
sudo mkdir -p /var/www
# Production tracks main
sudo git clone -b main    https://github.com/emretokatli/klicklocal.git /var/www/klicklocal
# Staging tracks develop
sudo git clone -b develop https://github.com/emretokatli/klicklocal.git /var/www/klicklocal-staging
sudo chown -R www-data:www-data /var/www/klicklocal /var/www/klicklocal-staging
```

## 4. Backend (run per environment)

Production (`/var/www/klicklocal/backend`):

```bash
cd /var/www/klicklocal/backend
composer install --no-dev --optimize-autoloader
cp ../deploy/env/backend.env.production.example .env
nano .env                       # set DB_PASSWORD + secrets
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache && php artisan route:cache
```

Staging is identical but uses `backend.env.staging.example` in
`/var/www/klicklocal-staging/backend`.

## 5. Frontend (run per environment)

Production:

```bash
cd /var/www/klicklocal/frontend
cp ../deploy/env/frontend.env.production.example .env.local
npm install && npm run build
```

Staging uses `frontend.env.staging.example` in `/var/www/klicklocal-staging/frontend`.

## 6. Processes (systemd + supervisor)

```bash
# Frontend (prod on :3000, staging on :3001)
sudo cp /var/www/klicklocal/deploy/systemd/klicklocal-frontend.service          /etc/systemd/system/
sudo cp /var/www/klicklocal/deploy/systemd/klicklocal-frontend-staging.service  /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now klicklocal-frontend klicklocal-frontend-staging

# Queue workers
sudo cp /var/www/klicklocal/deploy/supervisor/klicklocal-worker.conf          /etc/supervisor/conf.d/
sudo cp /var/www/klicklocal/deploy/supervisor/klicklocal-worker-staging.conf  /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
```

## 7. Nginx (six vhosts)

```bash
for c in api klicklocal admin api-test test admin-test; do
  f=$([ "$c" = "klicklocal" ] && echo klicklocal.app.conf || echo "$c.klicklocal.app.conf")
  sudo cp /var/www/klicklocal/deploy/nginx/$f /etc/nginx/sites-available/
  sudo ln -sf /etc/nginx/sites-available/$f /etc/nginx/sites-enabled/
done
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

## 8. HTTPS (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx \
  -d klicklocal.app -d www.klicklocal.app -d admin.klicklocal.app -d api.klicklocal.app \
  -d test.klicklocal.app -d admin-test.klicklocal.app -d api-test.klicklocal.app
sudo certbot renew --dry-run
```

## 9. Verify

```bash
curl https://api.klicklocal.app/up           # production health
curl https://api-test.klicklocal.app/up       # staging health
```

Then in a browser: `https://klicklocal.app` (customer), `https://admin.klicklocal.app/login`,
and the staging equivalents.

---

## Deploying new code

```bash
# Production — only from main
cd /var/www/klicklocal && sudo -u www-data git pull origin main
cd backend  && composer install --no-dev --optimize-autoloader && php artisan migrate --force \
            && php artisan config:cache && php artisan route:cache
cd ../frontend && npm install && npm run build
sudo systemctl restart klicklocal-frontend && sudo supervisorctl restart klicklocal-worker

# Staging — only from develop
cd /var/www/klicklocal-staging && sudo -u www-data git pull origin develop
cd backend  && composer install --no-dev --optimize-autoloader && php artisan migrate --force \
            && php artisan config:cache && php artisan route:cache
cd ../frontend && npm install && npm run build
sudo systemctl restart klicklocal-frontend-staging && sudo supervisorctl restart klicklocal-worker-staging
```

## Instagram OAuth redirect URIs

Register both in the Meta app (see `docs/META-INSTAGRAM-SETUP.md`):

```
https://api.klicklocal.app/api/v1/social-accounts/instagram/callback        (production)
https://api-test.klicklocal.app/api/v1/social-accounts/instagram/callback   (staging)
```

## Troubleshooting

| Symptom | Likely fix |
|---------|-----------|
| `502` on an api domain | Wrong PHP socket in `fastcgi_pass`; re-check `ls /run/php/` |
| `502` on app/admin domain | Next.js not running: `systemctl status klicklocal-frontend[-staging]` |
| "Cannot reach the API server" | `BACKEND_API_URL` wrong in that env's `frontend/.env.local`; rebuild + restart |
| `500` from Laravel | `storage/` not writable, or run `php artisan config:clear`; check `laravel.log` |
| DB connection error | `DB_PASSWORD` mismatch, or prod/staging pointing at the wrong database |
