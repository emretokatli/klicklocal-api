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

## Deployment

Two isolated environments, deployed by branch:

| Environment | Branch | Customer app | Admin | API |
|-------------|--------|--------------|-------|-----|
| Production | `main` | `https://klicklocal.app` | `https://admin.klicklocal.app` | `https://api.klicklocal.app` |
| Staging | `develop` | `https://test.klicklocal.app` | `https://admin-test.klicklocal.app` | `https://api-test.klicklocal.app` |

Rules: no localhost URLs in deployed code, all endpoints from env vars, and
production/staging databases stay isolated.

- **Server runbook:** [deploy/README.md](deploy/README.md)
- **Staging overview:** [docs/UAT.md](docs/UAT.md)
- **Frontend on Vercel:** [docs/VERCEL-DEPLOY.md](docs/VERCEL-DEPLOY.md)

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
