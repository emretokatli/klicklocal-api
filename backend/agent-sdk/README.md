# Klicklocal Website Analyze — Claude Agent SDK worker

Runs the `klicklocal-webanalyze` skill from `.claude/skills/klicklocal-webanalyze/SKILL.md`.

## Setup

```bash
cd backend/agent-sdk
npm install
php artisan migrate
```

Set in `backend/.env`:

```env
WEBANALYZE_DRIVER=api
ANTHROPIC_API_KEY=sk-ant-...
WEBANALYZE_NODE_BINARY=node
WEBANALYZE_TIMEOUT=900
WEBANALYZE_MAX_TURNS=12
WEBANALYZE_MAX_BUDGET_USD=0.5
QUEUE_CONNECTION=database
```

## Queue worker (required)

Analysis runs in a background job — **not** in the HTTP request (avoids PHP 120s timeout).

```bash
cd backend
php artisan queue:work
```

Leave this running while using `/admin/website-analyze`.

## Cost guardrails

Defaults cap spend per run:

| Setting | Default | Purpose |
|---------|---------|---------|
| `WEBANALYZE_MAX_TURNS` | 12 | Limits agent tool loops |
| `WEBANALYZE_MAX_BUDGET_USD` | 0.50 | Stops run when estimated cost reached |

Your previous run used ~673k input tokens because `WEBANALYZE_MAX_TURNS=40` with no budget cap.

## Manual test

From the monorepo root:

```bash
cd backend/agent-sdk
set ANTHROPIC_API_KEY=sk-ant-...
node analyze-website.mjs https://example-gastro.de
```

Stdout is JSON with `report_markdown`.

## API

- `POST /api/v1/admin/website-analyze` → `{ run: { id, status, ... } }`
- `GET /api/v1/admin/website-analyze/{id}` → poll until `completed` or `failed`

Partial reports are returned when the agent hits budget/turn limits but produced markdown.
