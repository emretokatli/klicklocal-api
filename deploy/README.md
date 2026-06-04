# Gastrocycle - Ubuntu (Hetzner) Deployment Runbook

Step-by-step guide to publish Klicklocal on a single Hetzner Ubuntu server:

| Domain | What runs there |
|--------|-----------------|
| `gastrocycle.com` | Landing page (Next.js `/`) |
| `admin.gastrocycle.com` | Admin dashboard (same Next.js app) |
| `api.gastrocycle.com` | Laravel API |

You run **every command below on the server**, logged in over SSH. Lines starting
with `sudo` need admin rights (you already are `root`, so `sudo` is optional but harmless).

> Files referenced here live in this `deploy/` folder. After you clone the repo on the
> server (step 3), they are at `/var/www/klicklocal/deploy/...`.

---

## 0. Connect to the server

From your Windows PowerShell (or the Hetzner Console "Console" button):

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

node : v18.19.1
npm : 9.2.0
PHP : 8.3.6 (cli)
ngnix : 1.24.0(Ubuntu)
mysql : Ver 8.0.46-0ubuntu0.24.04.2 for Linux x86_64 ((Ubuntu))
composer : version 2.7.1

Update the package list and install everything Klicklocal needs:

```bash
sudo apt update && sudo apt upgrade -y

sudo apt install -y nginx mysql-server \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd \
  git unzip curl supervisor

# Composer (PHP dependency manager)
sudo apt install -y composer

# Node.js 22 LTS (for the Next.js frontend)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo bash -
sudo apt install -y nodejs
```

Find your exact PHP-FPM socket name (you will need it for Nginx in step 8):

```bash
ls /run/php/
# Example output: php8.3-fpm.sock
```

If it is **not** `php8.3-fpm.sock`, edit `deploy/nginx/api.gastrocycle.com.conf`
and fix the `fastcgi_pass` line accordingly.

---

## 2. Confirm DNS points to this server

All three names must resolve to the server IP `167.233.19.131`:

```bash
dig +short gastrocycle.com
dig +short api.gastrocycle.com
dig +short admin.gastrocycle.com
```

Each should print `167.233.19.131`. If `dig` is missing: `sudo apt install -y dnsutils`.

> If a name does not resolve yet, fix the DNS A record at your domain provider and
> wait for it to propagate before doing SSL in step 9.

---

## 3. Get the code onto the server

```bash
sudo mkdir -p /var/www
cd /var/www
sudo git clone https://github.com/emretokatli/klicklocal.git klicklocal
cd /var/www/klicklocal
```

Replace `<YOUR_GIT_REPO_URL>` with your repo (e.g. `https://github.com/emretokatli/klicklocal.git`).
If the repo is private, GitHub will ask for a username + a **personal access token** (not your password).

Give the web server user ownership (so PHP and Next.js can read/write):

```bash
sudo chown -R www-data:www-data /var/www/klicklocal
```

---

## 4. Create the database

Open MySQL:

```bash
sudo mysql
```

Paste this (change the password to something strong and keep it for step 5):

```sql
CREATE DATABASE klicklocal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'klicklocal'@'localhost' IDENTIFIED BY 'yjvAfK.GGSE32Um';
GRANT ALL PRIVILEGES ON klicklocal.* TO 'klicklocal'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 5. Set up the Laravel backend

```bash
cd /var/www/klicklocal/backend

# Install PHP dependencies (production, no dev tools)
composer install --no-dev --optimize-autoloader

# Create the production .env from the template in this repo
cp ../deploy/env/backend.env.production.example .env
```

Now edit `.env` and set `DB_PASSWORD` to the password from step 4:

```bash
nano .env
```

(In nano: edit, then `Ctrl+O`, `Enter` to save, `Ctrl+X` to exit.)

Generate the app key and finish setup:

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

Make sure Laravel can write to its folders:

```bash
sudo chown -R www-data:www-data /var/www/klicklocal/backend/storage /var/www/klicklocal/backend/bootstrap/cache
sudo chmod -R 775 /var/www/klicklocal/backend/storage /var/www/klicklocal/backend/bootstrap/cache
```

The seed creates an admin login: `admin@klicklocal.test` / `password`.

---

## 6. Set up the Next.js frontend

```bash
cd /var/www/klicklocal/frontendf

# Production env (proxy mode -> talks to api.gastrocycle.com server-side)
cp ../deploy/env/frontend.env.production.example .env.local

npm install
npm run build
```

---

## 7. Keep the frontend + queue worker always running

**Frontend (systemd - recommended):**

```bash
sudo cp /var/www/klicklocal/deploy/systemd/klicklocal-frontend.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now klicklocal-frontend
sudo systemctl status klicklocal-frontend     # should say "active (running)"
```

The app now listens on `127.0.0.1:3000`. Logs: `sudo journalctl -u klicklocal-frontend -f`.

> Prefer pm2 instead? `sudo npm install -g pm2`, then
> `cd /var/www/klicklocal/frontend && pm2 start npm --name klicklocal-frontend -- start && pm2 save && pm2 startup`.
> Use one or the other, not both.

**Queue worker (Supervisor - for scheduled posts / publishing):**

```bash
sudo cp /var/www/klicklocal/deploy/supervisor/klicklocal-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status                      # should show klicklocal-worker RUNNING
```

---

## 8. Wire up Nginx (the three domains)

```bash
# Copy all three site configs
sudo cp /var/www/klicklocal/deploy/nginx/api.gastrocycle.com.conf   /etc/nginx/sites-available/
sudo cp /var/www/klicklocal/deploy/nginx/gastrocycle.com.conf       /etc/nginx/sites-available/
sudo cp /var/www/klicklocal/deploy/nginx/admin.gastrocycle.com.conf /etc/nginx/sites-available/

# Enable them
sudo ln -s /etc/nginx/sites-available/api.gastrocycle.com.conf   /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/gastrocycle.com.conf       /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/admin.gastrocycle.com.conf /etc/nginx/sites-enabled/

# Optional: remove the default placeholder site
sudo rm -f /etc/nginx/sites-enabled/default

# Test the config, then reload
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

Choose "redirect HTTP to HTTPS" when asked. Then confirm auto-renewal works:

```bash
sudo certbot renew --dry-run
```

---

## 10. Test everything

**Backend health** (open in a browser or use curl):

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

Expected: a JSON response containing a token.

**In the browser:**

1. `https://gastrocycle.com/` -> landing page loads.
2. `https://admin.gastrocycle.com/login` -> sign in with `admin@klicklocal.test` / `password`.
3. Dashboard loads workspaces + billing.
4. Create a workspace, upload media, create a post, schedule it, then "Publish now".

If something fails, check logs:

```bash
sudo tail -n 50 /var/www/klicklocal/backend/storage/logs/laravel.log
sudo journalctl -u klicklocal-frontend -n 50
sudo tail -n 50 /var/log/nginx/error.log
```

---

## 11. Instagram (do this later, after the basics work)

In the Meta app, register this exact OAuth redirect URI:

```
https://api.gastrocycle.com/api/v1/social-accounts/instagram/callback
```

Then in `backend/.env` set:

```env
SOCIAL_INSTAGRAM_DRIVER=api
INSTAGRAM_ENABLED=true
INSTAGRAM_APP_ID=your_instagram_app_id
INSTAGRAM_APP_SECRET=your_instagram_app_secret
```

Apply the changes:

```bash
cd /var/www/klicklocal/backend
php artisan config:clear
php artisan config:cache
```

---

## Updating later (new code from git)

```bash
cd /var/www/klicklocal
sudo -u www-data git pull

cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache
sudo supervisorctl restart klicklocal-worker

cd ../frontend
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
| Login fails / "Cannot reach the API server" | `BACKEND_API_URL` wrong in `frontend/.env.local`; rebuild + restart |
| `500` from Laravel | `storage/` not writable, or run `php artisan config:clear`; check `laravel.log` |
| SSL fails in certbot | DNS not yet pointing to the server (step 2), or port 80 blocked |
| DB connection error | `DB_PASSWORD` in `.env` does not match the MySQL user from step 4 |
