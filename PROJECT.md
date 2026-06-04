# Klicklocal Scheduler SaaS - Project Guide

## Overview

Klicklocal is an API-first SaaS platform for creating, scheduling, and publishing social media posts from web and mobile clients.

The system consists of:

- Laravel API backend
- Next.js SaaS dashboard
- Expo React Native mobile app
- Queue workers for scheduled publishing
- Social media provider integrations
- Workspace-scoped billing, usage, and admin tooling

Both the web dashboard and mobile application must use the same Laravel API. Backend services own business logic, scheduling, billing rules, permissions, and social API communication.

---

## Product Goals

Build a scalable scheduling platform similar to Buffer, Hootsuite, Later, Stackposts, and Postiz.

Core functionality:

- Create and schedule posts
- Upload and attach media
- Publish immediately or through queues
- Connect social accounts, starting with Instagram
- Manage workspaces and workspace roles
- Track plan limits and usage
- Manage subscriptions, invoices, transactions, and coupons
- Provide admin tooling for platform operations
- Keep web and mobile clients synchronized through the API

---

## Current Implementation Status

Implemented:

- Laravel 12 API with Sanctum token authentication
- Workspace CRUD and workspace-scoped team roles
- Spatie permission-based platform and workspace authorization
- Post CRUD, scheduling, publish-now flow, and queue job publishing
- Media upload and media library APIs
- Instagram OAuth connection and publishing flow
- Social provider abstraction with fake, Instagram, and LinkedIn provider classes
- Admin-managed social provider settings for Facebook, Instagram, and TikTok
- Workspace-scoped billing with plans, plan features, subscriptions, usage, invoices, transactions, coupons, and coupon redemptions
- Stripe webhook handling for billing events
- Admin APIs for users, workspaces, plans, subscriptions, coupons, transactions, usage, settings, AI prompts, and social providers
- AI prompt template management
- Next.js dashboard with React Query, reusable UI components, admin/customer dashboards, billing, posts, media, calendar, settings, and social accounts
- Next.js same-origin `/api/v1` proxy to Laravel
- Expo Router mobile app with authentication and workspace API integration
- UAT, Vercel deployment, and Meta Instagram setup documentation

Not implemented or still future-facing:

- Dedicated notifications feature and notifications table
- Dedicated analytics tables beyond usage/admin reporting
- Production-ready publishing flows for every social provider
- Full AI caption generation workflow
- Team approvals
- Automation workflows

---

## Technology Stack

### Backend

- PHP `^8.2`
- Laravel 12
- MySQL
- Laravel Sanctum
- Laravel Queue
- Spatie Laravel Permission
- L5 Swagger
- Stripe-ready billing services

### Frontend

- Next.js 16
- React 19
- TypeScript
- Tailwind CSS 4
- Radix UI primitives
- React Query
- Axios

### Mobile

- Expo 54
- Expo Router 6
- React Native 0.81
- TypeScript
- NativeWind
- React Query
- Expo Secure Store

### Storage

- Local Laravel public storage for development and current deployment
- `NEXT_PUBLIC_STORAGE_URL` / backend storage URL used by clients
- Supabase Storage is not currently implemented in the codebase

---

## Architecture Rules

Important:

- Backend contains all business logic.
- Mobile app and web app are API clients.
- Do not duplicate scheduling, billing, authorization, or social-provider logic in clients.
- All scheduling must happen inside Laravel backend services and jobs.
- All social API communication must happen inside Laravel backend services.
- Controllers should stay thin and delegate to services/actions.
- Use Form Requests for validation where applicable.
- Use reusable UI components in frontend/mobile clients.
- Use React Query for client/server state.

---

## High-Level Architecture

```txt
Expo React Native App
        |
        v
Laravel API Backend <---- Queue Worker
        ^
        |
Next.js SaaS Dashboard
```

The Next.js app can call Laravel directly or use its same-origin proxy:

```txt
Browser -> Next.js /api/v1/* -> Laravel /api/v1/*
```

The proxy is implemented at `frontend/src/app/api/v1/[...path]/route.ts`.

---

## Backend Responsibilities

Laravel handles:

- Authentication and bearer tokens
- Authorization, platform roles, workspace roles, and permissions
- Workspace lifecycle
- Post creation, scheduling, and publishing
- Queue workers
- Media processing and storage
- Social account OAuth and provider API calls
- Billing, subscriptions, usage, invoices, transactions, and coupons
- Stripe webhooks
- AI prompt templates
- Admin APIs
- API documentation through Swagger

---

## Frontend Responsibilities

The Next.js dashboard handles:

- Marketing landing page
- Login and registration
- Dashboard layout and navigation
- Workspace selection
- Posts, calendar, media, billing, usage, invoices, settings, and social accounts UI
- Admin UI for users, workspaces, plans, subscriptions, coupons, transactions, usage, settings, AI prompts, and providers
- Client-side auth token storage
- API consumption through service modules and React Query

Frontend must never directly communicate with social media APIs or own billing/authorization decisions.

---

## Mobile Responsibilities

The Expo app handles:

- Authentication
- Secure token storage
- Workspace listing and creation
- Mobile-first API consumption
- Future post creation, scheduling, media upload, and push notification UX

The mobile app must only use backend APIs.

---

## Repository Structure

```txt
backend/              Laravel 12 API
frontend/             Next.js dashboard
mobile/               Expo Router mobile app
docs/                 Deployment, UAT, and Meta setup docs
www/                  Web server/public hosting support
README.md             Quick start
PROJECT.md            Current architecture and roadmap
```

---

## Backend Structure

Important backend folders:

```txt
backend/app/
├── Actions/
├── Contracts/
├── Enums/
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Middleware/
│   └── Requests/
├── Jobs/
├── Models/
├── OpenApi/
├── Policies/
├── Providers/
├── Services/
│   ├── Ai/
│   ├── Auth/
│   ├── Authorization/
│   ├── Billing/
│   ├── Media/
│   ├── Post/
│   ├── SocialProviders/
│   ├── Subscription/
│   ├── Usage/
│   └── Workspace/
└── Support/
```

Important backend models:

- `User`
- `Workspace`
- `WorkspaceMember`
- `Post`
- `PostPlatform`
- `Media`
- `SocialAccount`
- `OAuthState`
- `Plan`
- `PlanFeature`
- `Subscription`
- `SubscriptionUsage`
- `Invoice`
- `Transaction`
- `Coupon`
- `CouponRedemption`
- `UsageRecord`
- `AiPromptTemplate`

---

## API Structure

All backend APIs are versioned under:

```txt
/api/v1
```

Public or unauthenticated endpoints:

```txt
POST /api/v1/auth/register
POST /api/v1/auth/login
GET  /api/v1/social-accounts/instagram/callback
POST /api/v1/webhooks/stripe
```

Authenticated customer endpoints:

```txt
POST   /api/v1/auth/logout
GET    /api/v1/auth/me

GET    /api/v1/workspaces
POST   /api/v1/workspaces
GET    /api/v1/workspaces/{workspace}
PUT    /api/v1/workspaces/{workspace}
DELETE /api/v1/workspaces/{workspace}

GET    /api/v1/posts
POST   /api/v1/posts
GET    /api/v1/posts/{post}
PUT    /api/v1/posts/{post}
DELETE /api/v1/posts/{post}
POST   /api/v1/posts/{post}/schedule
POST   /api/v1/posts/{post}/publish

GET    /api/v1/media
POST   /api/v1/media/upload

GET    /api/v1/billing
GET    /api/v1/subscription
POST   /api/v1/subscription
POST   /api/v1/subscription/cancel
GET    /api/v1/usage
GET    /api/v1/invoices

GET    /api/v1/social-accounts/instagram/connect
POST   /api/v1/social-accounts/instagram/disconnect
GET    /api/v1/social-accounts/instagram/status
```

Admin endpoints require platform admin authorization:

```txt
GET    /api/v1/admin/users
GET    /api/v1/admin/users/{user}
PUT    /api/v1/admin/users/{user}/roles

GET    /api/v1/admin/workspaces

GET    /api/v1/admin/plans
POST   /api/v1/admin/plans
PUT    /api/v1/admin/plans/{plan}
DELETE /api/v1/admin/plans/{plan}
GET    /api/v1/admin/plans/feature-keys

GET    /api/v1/admin/subscriptions
POST   /api/v1/admin/subscriptions
DELETE /api/v1/admin/subscriptions/{subscription}

GET    /api/v1/admin/transactions
GET    /api/v1/admin/coupons
POST   /api/v1/admin/coupons
PUT    /api/v1/admin/coupons/{coupon}

GET    /api/v1/admin/settings
PUT    /api/v1/admin/settings

GET    /api/v1/admin/ai-prompts
POST   /api/v1/admin/ai-prompts
GET    /api/v1/admin/ai-prompts/{aiPromptTemplate}
PUT    /api/v1/admin/ai-prompts/{aiPromptTemplate}
PATCH  /api/v1/admin/ai-prompts/{aiPromptTemplate}/active

GET    /api/v1/admin/usage

GET    /api/v1/admin/providers
PUT    /api/v1/admin/providers/{provider}
PUT    /api/v1/admin/providers/instagram
```

---

## Authentication

The API uses Laravel Sanctum bearer tokens.

Authentication flow:

1. User registers or logs in.
2. Backend returns a bearer token.
3. Web stores the token through `frontend/src/lib/token.ts`.
4. Mobile stores the token through Expo Secure Store.
5. Authenticated requests send `Authorization: Bearer <token>`.
6. Logout revokes the backend token and clears client storage.

---

## Authorization And Roles

Authorization uses Spatie Laravel Permission plus application middleware and policies.

Platform roles:

- `super_admin`
- `admin`
- `support`

Workspace roles:

- `owner`
- `manager`
- `editor`
- `viewer`

Important permission groups:

- Platform: users, plans, subscriptions, AI prompts, social providers, platform settings, admin dashboard, usage analytics
- Workspace: workspace management, members, posts, scheduling, media, social accounts

---

## Queue System

Scheduled and publish-now flows use backend services and `PublishPostJob`.

Publishing flow:

1. User creates a post.
2. User schedules or publishes the post.
3. Backend validates workspace, permissions, quotas, and post data.
4. Backend syncs target post platforms.
5. Queue worker runs `PublishPostJob` at publish time.
6. Social provider service publishes content.
7. Backend stores provider response metadata and status.

Run a local worker with:

```bash
cd backend
php artisan queue:work
```

For quick local tests, `QUEUE_CONNECTION=sync` can be used.

---

## Social Provider Architecture

Social integrations use provider services behind backend abstractions. Controllers must not call provider APIs directly.

Important paths:

```txt
backend/app/Services/SocialProviders/
├── Base/
├── Contracts/
├── DTOs/
├── Exceptions/
├── Factory/
├── Fake/
├── Facebook/
├── Instagram/
├── LinkedIn/
├── AbstractSocialProviderSettingsService.php
├── SocialProviderSettingsRegistry.php
└── SocialProviderSettingsService implementations
```

Current provider state:

- Instagram has OAuth, account status, disconnect, API provider, and publishing services.
- Fake Facebook, Instagram, and LinkedIn providers support local/test flows.
- LinkedIn API provider classes exist.
- Admin provider settings exist for Facebook, Instagram, and TikTok.
- Additional production provider flows remain future work.

See:

- `docs/META-INSTAGRAM-SETUP.md`
- `docs/META-APP-REVIEW.md`
- `backend/docs/INSTAGRAM.md`

---

## Billing And Usage

Billing is workspace-scoped. Each workspace can have an active subscription tied to a plan and normalized plan features.

Billing tables:

- `plans`
- `plan_features`
- `subscriptions`
- `subscription_usage`
- `transactions`
- `invoices`
- `coupons`
- `coupon_redemptions`

Billing services handle:

- Plan and feature access
- Subscription lifecycle
- Usage metering
- Invoice records
- Transaction records
- Coupon management
- Stripe webhook sync

Important middleware:

- `feature.quota:{key}`
- `workspace.subscription`
- `stripe.webhook`

Creating a workspace auto-starts a Starter trial subscription when the plan exists in the database.

See `backend/docs/BILLING.md`.

---

## Database Notes

Use Laravel migrations as the source of truth. Do not manually maintain standalone SQL table definitions in this guide.

Important migration groups currently include:

- Laravel users, cache, jobs, and Sanctum personal access tokens
- Workspaces and workspace members
- Social accounts and OAuth states
- Posts and post platforms
- Media
- Spatie permission tables
- Plans, plan features, subscriptions, usage records, and subscription usage
- Billing architecture tables for transactions, invoices, coupons, and coupon redemptions
- AI prompt templates
- Instagram social account extensions
- Post processing and publish metadata

Tables previously described but not currently present as migrations:

- `notifications`
- dedicated `analytics`

Usage/admin reporting exists, but it is not the same as a dedicated social analytics table.

---

## Local Development

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

Local backend/API reference:

```txt
http://localhost:1981/klicklocal/backend/public/api/v1
```

Swagger UI:

```txt
http://localhost:1981/klicklocal/backend/public/api/documentation
```

### Frontend

```bash
cd frontend
npm install
cp .env.local.example .env.local
npm run dev
```

Recommended local frontend API mode:

```env
NEXT_PUBLIC_API_URL=/api/v1
BACKEND_API_URL=http://localhost:1981/klicklocal/backend/public/api/v1
NEXT_PUBLIC_STORAGE_URL=http://localhost:1981/klicklocal/backend/public/storage
```

### Mobile

```bash
cd mobile
npm install
cp .env.example .env
npm start
```

Mobile API env:

```env
EXPO_PUBLIC_API_URL=http://192.168.178.20:1981/klicklocal/backend/public/api/v1
```

Use the correct LAN IP for the development machine when testing on a physical device.

---

## UAT And Deployment

UAT and deployment docs:

- `docs/UAT.md`
- `docs/UAT-WEBSPACE-DEPLOY.md`
- `docs/UAT-500-TROUBLESHOOTING.md`
- `docs/VERCEL-DEPLOY.md`

Frontend production/UAT supports two API modes:

- Recommended: `NEXT_PUBLIC_API_URL=/api/v1` with `BACKEND_API_URL` set server-side for the Next.js proxy.
- Alternative: browser calls Laravel directly with CORS configured on the backend.

Current UAT examples use:

```txt
https://gastrocycle.com/public/api/v1
```

---

## Testing

Backend tests currently cover:

- Auth API
- Workspace API
- Post API
- Media API
- Admin API
- Instagram social account flow
- Post scheduling service
- Publish post job
- Social provider factory and fake providers
- Billing feature access
- Instagram publishing service
- Post platform publishing service

Run:

```bash
cd backend
php artisan test
```

Frontend:

```bash
cd frontend
npm run lint
npm run build
```

Mobile:

```bash
cd mobile
npm start
```

---

## Roadmap

Near-term:

1. Complete customer-facing post creation and scheduling workflows across web and mobile.
2. Harden Instagram production publishing and Meta app review requirements.
3. Finish billing UX and subscription management flows.
4. Expand mobile beyond auth/workspaces into posts, media, and scheduling.
5. Add notification model/API/UX if product requires it.

Later:

1. Add production integrations for more social providers.
2. Add dedicated social analytics storage and reports.
3. Add AI caption generation using managed prompt templates.
4. Add team approvals.
5. Add automation workflows.

---

## Development Guidance

- Keep backend service boundaries strong.
- Keep controllers thin.
- Prefer policies, middleware, and service invariants over duplicated client checks.
- Keep frontend/mobile UI reusable and mobile-first.
- Use React Query for remote data.
- Use the existing API client abstractions instead of ad hoc fetch calls.
- Treat Laravel migrations and route files as the source of truth when updating this guide.
