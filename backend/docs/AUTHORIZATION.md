# Authorization Architecture (Klicklocal SaaS)

## Overview

Authorization uses **Spatie Laravel Permission** with **teams** enabled. `workspace_id` is the team foreign key. Platform-scoped roles use team id `0` (`TeamContext::PLATFORM`); workspace members use the workspace id.

| Layer | Scope | Roles |
|-------|--------|-------|
| Platform | Global (`workspace_id = null`) | `super_admin`, `admin`, `support` |
| Workspace | Per workspace team | `owner`, `manager`, `editor`, `viewer` |

Permission names live in `App\Support\Permission`. Role maps live in `App\Support\PlatformRole` and `Permission::workspaceRolePermissionMap()`.

## Middleware

| Alias | Class | Purpose |
|-------|--------|---------|
| `platform.admin` | `EnsurePlatformAdmin` | Admin API routes |
| `customer` | `EnsureCustomer` | Authenticated customer routes |
| `workspace.team` | `SetWorkspaceTeam` | Validates workspace access; sets Spatie team from `workspace_id` query/body or `X-Workspace-Id` |
| `permission` | `EnsurePermission` | Route-level permission check (`permission:manage_users`) |

## Policies

Workspace isolation is enforced in policies via `AuthorizationService::hasWorkspacePermission()`. Owners always pass workspace checks.

- `WorkspacePolicy`, `PostPolicy`, `MediaPolicy` — customer resources
- `UserPolicy`, `PlanPolicy`, `SubscriptionPolicy`, `AiPromptTemplatePolicy` — admin resources

## Services

- `AuthorizationService` — team context, platform/workspace permission checks, `/auth/me` abilities
- `WorkspaceRoleSyncService` — syncs Spatie roles when workspace members are assigned
- `UsageTrackingService` — AI, social API, queue jobs, storage
- `SubscriptionService` / `PlanService` — plans, subscriptions, limits (billing-ready)

## Admin API (`/api/v1/admin/*`)

Requires `auth:sanctum` + `platform.admin`.

| Endpoint | Permission |
|----------|------------|
| `GET/PUT users` | `manage_users` |
| `GET/POST plans` | `manage_plans` |
| `GET/POST subscriptions` | `manage_subscriptions` |
| `GET/PUT settings` | `manage_platform_settings` |
| `GET/POST/PUT ai-prompts` | `manage_ai_prompts` |
| `GET usage` | `view_usage_analytics` |

## Seeding

```bash
php artisan migrate
php artisan db:seed
```

Default platform admin: `admin@klicklocal.test` / `password` (override via `PLATFORM_ADMIN_EMAIL`, `PLATFORM_ADMIN_PASSWORD`).

## Customer `/auth/me` response

Returns `user`, `abilities` (platform + optional workspace), `is_platform_admin`, and `subscription_limits`.

Pass `?workspace_id=` to include workspace role and permissions.
