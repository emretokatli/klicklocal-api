# Scheduler SaaS — Backend API

Laravel 12 API with Sanctum authentication.

## Requirements

- PHP 8.2+
- Composer
- MySQL 8
- XAMPP (or Apache) on port **1981**

## Setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Create the database:

```sql
CREATE DATABASE klicklocal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Update `.env` with your MySQL credentials, then:

```bash
php artisan migrate
php artisan storage:link
```

API base URL: `http://localhost:1981/klicklocal/backend/public/api/v1`

## Swagger UI

Interactive API documentation (Swagger UI):

- **http://localhost:1981/klicklocal/backend/public/api/documentation**
- Shortcut: **http://localhost:1981/klicklocal/backend/public/swagger** (redirects to Swagger UI)

Do not add a `/docs` redirect — that path serves the OpenAPI JSON file for Swagger UI.

1. Open Swagger UI in your browser.
2. Call **POST /auth/login** or **POST /auth/register** to get a token.
3. Click **Authorize**, enter `Bearer YOUR_TOKEN` (or just the token if the UI adds Bearer).
4. Try protected endpoints.

Regenerate docs after API changes:

```bash
php artisan l5-swagger:generate
```

Set `L5_SWAGGER_CONST_HOST` in `.env` if your public URL path differs from the default XAMPP layout.

## Auth Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/auth/register` | No | Register a new user |
| POST | `/api/v1/auth/login` | No | Login and receive bearer token |
| POST | `/api/v1/auth/logout` | Bearer | Revoke current token |
| GET | `/api/v1/auth/me` | Bearer | Get authenticated user |

## Workspace Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/workspaces` | List user workspaces |
| POST | `/api/v1/workspaces` | Create workspace |
| GET | `/api/v1/workspaces/{id}` | Show workspace |
| PUT | `/api/v1/workspaces/{id}` | Update workspace |
| DELETE | `/api/v1/workspaces/{id}` | Delete workspace |

## Post Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/posts?workspace_id=1` | List posts |
| POST | `/api/v1/posts` | Create draft post |
| GET | `/api/v1/posts/{id}` | Show post |
| PUT | `/api/v1/posts/{id}` | Update post |
| DELETE | `/api/v1/posts/{id}` | Delete post |
| POST | `/api/v1/posts/{id}/schedule` | Schedule post |

## Media Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/media/upload` | Upload image/video (multipart) |

## Example Requests

**Register**

```bash
curl -X POST http://localhost:1981/klicklocal/backend/public/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"John Doe","email":"john@example.com","password":"password123","password_confirmation":"password123"}'
```

**Login**

```bash
curl -X POST http://localhost:1981/klicklocal/backend/public/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"password123"}'
```

**Create workspace**

```bash
curl -X POST http://localhost:1981/klicklocal/backend/public/api/v1/workspaces \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"My Workspace"}'
```

## Response Format

Success:

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "user": { "id": 1, "name": "John Doe", "email": "john@example.com" },
    "token": "1|..."
  }
}
```

## Queue worker

For scheduled publishing:

```bash
php artisan queue:work
```

## Tests

```bash
php artisan test
```
