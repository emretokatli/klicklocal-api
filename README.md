# Klicklocal — Scheduler SaaS

Social media scheduling platform (Buffer/Hootsuite-style).

## Project structure

| Folder | Description |
|--------|-------------|
| [backend/](backend/) | Laravel 12 API (Sanctum, workspaces, posts, media, queues) |
| [frontend/](frontend/) | Next.js dashboard |
| [mobile/](mobile/) | Expo React Native app |
| [PROJECT.md](PROJECT.md) | Full architecture and roadmap |

## Local environment

- PHP / Apache: **http://localhost:1981**
- MySQL database: **klicklocal**
- API base: `http://localhost:1981/klicklocal/backend/public/api/v1`

## UAT environment

See **[docs/UAT.md](docs/UAT.md)** and **[docs/UAT-WEBSPACE-DEPLOY.md](docs/UAT-WEBSPACE-DEPLOY.md)**. Quick activation:

```bash
cd backend && cp .env.uat .env && php artisan config:clear && php artisan db:seed
cd ../frontend && cp .env.uat.local .env.local
```

Set `YOUR_UAT_DOMAIN` in those env files to your public UAT hostname.

## Quick start

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan l5-swagger:generate
php artisan test
```

Swagger UI: http://localhost:1981/klicklocal/backend/public/api/documentation (shortcut: `/swagger`)

### Web dashboard

```bash
cd frontend
cp .env.local.example .env.local
npm run dev
```

### Mobile

```bash
cd mobile
npm install
cp .env.example .env
npm start
```

### Queue worker (scheduled posts)

```bash
cd backend
php artisan queue:work
```
