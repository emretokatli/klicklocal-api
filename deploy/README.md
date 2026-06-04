# Gastrocycle - Ubuntu (Hetzner) Deployment Runbook

Step-by-step guide to publish Klicklocal on a single Hetzner Ubuntu server.

There are **two separate Git repositories**:

| Repo | What it is | Server location | App root |
|------|------------|-----------------|----------|
| `klicklocal-api` | Laravel backend | `/var/www/klicklocal-api` | `/var/www/klicklocal-api/backend` |
| `klicklocal` | Next.js frontend | `/var/www/klicklocal` | `/var/www/klicklocal` (root) |

| Domain | What runs there |
|--------|-----------------|
| `gastrocycle.com` | Landing page (Next.js `/`) |
| `admin.gastrocycle.com` | Admin dashboard (same Next.js app) |
| `api.gastrocycle.com` | Laravel API |

You run **every command below on the server**, logged in over SSH. Lines starting
with `sudo` need admin rights (you are `root`, so `sudo` is optional but harmless).

> These `deploy/` config files live in the **`klicklocal-api`** repo. After step 3
> they are at `/var/www/klicklocal-api/deploy/...`.

---

## 0. Connect to the server

From Windows PowerShell (or the Hetzner Console "Console" button):

```bash
ssh root@167.233.19.131
```

Type `yes` the first time it asks about the fingerprint, then your password.

---

## 1. Verify what is installed + install the rest

Check versions (anything that says "command not found" still needs installing):

```bash
node -v
npm -v
php -v
nginx -v
mysql --version
composer --version
```

Klicklocal needs **Node 20+** (you have v22, good). Install the rest:

```bash
sudo apt update && sudo apt upgrade -y

sudo apt install -y nginx mysql-server \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd \
  git unzip curl supervisor
```

Find your exact PHP-FPM socket name (needed for Nginx in step 8):

```bash
ls /run/php/
# Example: php8.3-fpm.sock
```

If it is **not** `php8.3-fpm.sock`, edit `deploy/nginx/api.gastrocycle.com.conf`
and fix the `fastcgi_pass` line accordingly.

---

## 2. Confirm DNS points to this server

All three names must resolve to `167.233.19.131`:

```bash
dig +short gastrocycle.com
dig +short api.gastrocycle.com
dig +short admin.gastrocycle.com
```

If `dig` is missing: `sudo apt install -y dnsutils`. Fix any missing A record at
your domain provider before doing SSL in step 9.

---

## 3. Clone BOTH repos

```bash
sudo mkdir -p /var/www
cd /var/www

# Backend (Laravel lives in its backend/ subfolder)
sudo git clone https://github.com/emretokatli/klicklocal-api.git klicklocal-api

# Frontend (Next.js app is at the repo root)
sudo git clone https://github.com/emretokatli/klicklocal.git klicklocal
```

> If you previously cloned the wrong thing into `/var/www/klicklocal`, remove it first:
> `sudo rm -rf /var/www/klicklocal` then run the frontend clone above.

GitHub will ask for a username + a **personal access token** (not your password) if a repo is private.

Give the web server user ownership:

```bash
sudo chown -R www-data:www-data /var/www/klicklocal-api /var/www/klicklocal
```

Quick sanity check (both must print a path):

```bash
ls /var/www/klicklocal-api/backend/artisan
ls /var/www/klicklocal/package.json
```

---

## 4. Create the database

Open MySQL:

```bash
sudo mysql
```

Paste this (use underscores in the collation, change the password):

```sql
CREATE DATABASE klicklocal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'klicklocal'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON klicklocal.* TO 'klicklocal'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> Already done earlier with password `yJvAfK.GGSE32Um`? Then skip this step and
> reuse that password in step 5.

---

## 5. Set up the Laravel backend

```bash
cd /var/www/klicklocal-api/backend

composer install --no-dev --optimize-autoloader

# Production .env from the template (deploy/ is in the backend repo)
cp ../deploy/env/backend.env.production.example .env
nano .env        # set DB_PASSWORD to your MySQL password
```

(In nano: edit, `Ctrl+O`, `Enter` to save, `Ctrl+X` to exit.)

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

Make Laravel folders writable:

```bash
sudo chown -R www-data:www-data /var/www/klicklocal-api/backend/storage /var/www/klicklocal-api/backend/bootstrap/cache
sudo chmod -R 775 /var/www/klicklocal-api/backend/storage /var/www/klicklocal-api/backend/bootstrap/cache
```

Seed creates an admin login: `admin@klicklocal.test` / `password`.

---

## 6. Set up the Next.js frontend

```bash
cd /var/www/klicklocal

# Production env (proxy mode -> talks to api.gastrocycle.com server-side).
# The template lives in the backend repo's deploy/ folder:
cp /var/www/klicklocal-api/deploy/env/frontend.env.production.example .env.local

npm install
npm run build
```

---

## 7. Keep the frontend + queue worker always running

**Frontend (systemd):**

```bash
sudo cp /var/www/klicklocal-api/deploy/systemd/klicklocal-frontend.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now klicklocal-frontend
sudo systemctl status klicklocal-frontend     # should say "active (running)"
```

The app listens on `127.0.0.1:3000`. Logs: `sudo journalctl -u klicklocal-frontend -f`.

> Prefer pm2? `sudo npm install -g pm2`, then
> `cd /var/www/klicklocal && pm2 start npm --name klicklocal-frontend -- start && pm2 save && pm2 startup`.
> Use one or the other, not both.

**Queue worker (Supervisor - scheduled posts / publishing):**

```bash
sudo cp /var/www/klicklocal-api/deploy/supervisor/klicklocal-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status                      # klicklocal-worker RUNNING
```

---

## 8. Wire up Nginx (the three domains)

```bash
sudo cp /var/www/klicklocal-api/deploy/nginx/api.gastrocycle.com.conf   /etc/nginx/sites-available/
sudo cp /var/www/klicklocal-api/deploy/nginx/gastrocycle.com.conf       /etc/nginx/sites-available/
sudo cp /var/www/klicklocal-api/deploy/nginx/admin.gastrocycle.com.conf /etc/nginx/sites-available/

sudo ln -s /etc/nginx/sites-available/api.gastrocycle.com.conf   /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/gastrocycle.com.conf       /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/admin.gastrocycle.com.conf /etc/nginx/sites-enabled/

# Optional: remove the default placeholder site
sudo rm -f /etc/nginx/sites-enabled/default

sudo nginx -t
sudo systemctl reload nginx
```

If `nginx -t` complains about the PHP socket, re-check step 1 (`ls /run/php/`) and fix
the `fastcgi_pass` line in `api.gastrocycle.com.conf`, then copy + reload again.

---

## 9. Enable HTTPS (free SSL via Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx

sudo certbot --nginx \
  -d gastrocycle.com -d www.gastrocycle.com \
  -d api.gastrocycle.com -d admin.gastrocycle.com
```

Choose "redirect HTTP to HTTPS" when asked, then confirm auto-renewal:

```bash
sudo certbot renew --dry-run
```

---

## 10. Test everything

**Backend health:**

```bash
curl https://api.gastrocycle.com/up
```

**Login API:**

```bash
curl -X POST "https://api.gastrocycle.com/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@klicklocal.test","password":"password"}'
```

Expected: JSON with a token.

**In the browser:**

1. `https://gastrocycle.com/` -> landing page.
2. `https://admin.gastrocycle.com/login` -> sign in `admin@klicklocal.test` / `password`.
3. Dashboard loads workspaces + billing.
4. Create workspace, upload media, create a post, schedule, "Publish now".

Logs if something fails:

```bash
sudo tail -n 50 /var/www/klicklocal-api/backend/storage/logs/laravel.log
sudo journalctl -u klicklocal-frontend -n 50
sudo tail -n 50 /var/log/nginx/error.log
```

---

## 11. Instagram (later, after the basics work)

Register this exact OAuth redirect URI in the Meta app:

```
https://api.gastrocycle.com/api/v1/social-accounts/instagram/callback
```

Then in `/var/www/klicklocal-api/backend/.env`:

```env
SOCIAL_INSTAGRAM_DRIVER=api
INSTAGRAM_ENABLED=true
INSTAGRAM_APP_ID=your_instagram_app_id
INSTAGRAM_APP_SECRET=your_instagram_app_secret
```

Apply:

```bash
cd /var/www/klicklocal-api/backend
php artisan config:clear && php artisan config:cache
```

---

## Updating later (new code from git)

**Backend:**

```bash
cd /var/www/klicklocal-api
sudo -u www-data git pull
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache
sudo supervisorctl restart klicklocal-worker
```

**Frontend:**

```bash
cd /var/www/klicklocal
sudo -u www-data git pull
npm install
npm run build
sudo systemctl restart klicklocal-frontend
```

---

## Quick troubleshooting

| Symptom | Likely fix |
|---------|-----------|
| `502 Bad Gateway` on api domain | Wrong PHP socket in `fastcgi_pass`; re-check `ls /run/php/` |
| `502` on landing/admin domain | Next.js not running: `sudo systemctl status klicklocal-frontend` |
| `cd .../backend` "No such file" | You cloned the wrong repo; backend is in `klicklocal-api` (step 3) |
| Login fails / "Cannot reach the API server" | `BACKEND_API_URL` wrong in `/var/www/klicklocal/.env.local`; rebuild + restart |
| `500` from Laravel | `storage/` not writable, or run `php artisan config:clear`; check `laravel.log` |
| SSL fails in certbot | DNS not pointing to the server (step 2), or port 80 blocked |
| DB connection error | `DB_PASSWORD` in `.env` does not match the MySQL user from step 4 |
