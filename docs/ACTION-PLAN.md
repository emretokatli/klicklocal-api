# Klicklocal — Action Plan (based on GAP-ANALYSIS.md)

Date: 2026-06-10. Each task has an ID (T1, T2, …) — reference it when requesting a Claude Code prompt.
Legend: **CC** = Claude Code implements · **YOU** = Emre handles (accounts, approvals, keys, deploys, decisions).

General rule for every task: backend logic lives in Laravel services (driver pattern with fake/real implementations, like `SocialProviderFactory` and `OpenAiClientInterface`), clients stay thin API consumers, customer copy in German via `de.ts`.

---

## Phase 0 — Critical mobile fixes (blockers, do first)

### T1 — Fix mobile registration (422)
- **What:** Add a name field to `register.tsx` (or derive from business name), send valid `first_name`; unify the duplicate register logic in `auth-provider.tsx` and `features/auth/api.ts`; add an industry picker instead of hardcoded `'Sonstiges'`.
- **Stack:** Expo 54 / React Native / TypeScript, existing `api-client.ts`. No backend change.
- **CC:** all code. **YOU:** test on a device/emulator against staging API.

### T2 — Respect onboarding state on mobile login
- **What:** Use `onboarding_completed` from `/auth/login`; route incomplete users to a mobile onboarding screen (minimal: complete profile via `PATCH /auth/onboarding` + `/auth/onboarding/complete`); handle the no-workspace case instead of `workspaceId: 0`.
- **Stack:** Expo Router 6 route guards, React Query, existing auth endpoints.
- **CC:** all code. **YOU:** decide whether mobile gets the full 12-step wizard or a shortened 3–4-step version (recommendation: shortened, reuse web for the rest).

### T3 — Secure token storage
- **What:** Replace AsyncStorage session persistence with `expo-secure-store` (already installed); keep non-sensitive prefs in AsyncStorage if needed; migration path for existing sessions.
- **Stack:** expo-secure-store ~15, TypeScript.
- **CC:** all code. **YOU:** nothing.

---

## Phase 1 — Mobile core loop (describe → photo → generate → schedule)

### T4 — Real prompt input + image upload
- **What:** Turn the static "Beschreibe deine Idee" text into a TextInput; add image picking/upload via `POST /media/upload`; pass `prompt` + `media_id` to `/ai/generate`.
- **Stack:** `expo-image-picker`, multipart upload through existing api-client, React Query mutation.
- **CC:** all code. **YOU:** confirm `expo-image-picker` addition to `package.json` and rebuild the dev client if needed.

### T5 — Save generation as draft + schedule + calendar
- **What:** "Übernehmen" action that creates a draft via `POST /posts` (caption, hashtags, media, platform); build the real Schedule screen (`POST /posts/{id}/schedule` with datetime picker) and Calendar screen (posts by `scheduled_at`); remove hardcoded times/week strip in the Plan tab.
- **Stack:** Expo, `@react-native-community/datetimepicker` (or expo equivalent), React Query, existing post endpoints. No backend change.
- **CC:** all code. **YOU:** UX sign-off on the scheduling flow.

### T6 — Subscription awareness + 402 handling on mobile
- **What:** Global 402 handler in `api-client.ts` → upgrade screen; real Abonnement screen reading `GET /subscription`, `GET /usage`, `GET /quota/packages`; deep link to web billing for payment (no in-app purchase logic for now).
- **Stack:** Expo, React Query, existing billing endpoints.
- **YOU (decision):** Apple/Google will require in-app purchases if you sell digital subscriptions inside the app — for store release you must either use RevenueCat/StoreKit/Play Billing or keep purchase strictly on the web (reader-app style). Decide before store submission.
- **CC:** all client code once you decide.

---

## Phase 2 — Comments pipeline (the "community engagement" concept core)

### T7 — Comment ingestion from platforms (backend)
- **What:** Extend the social provider contracts with `fetchComments()`; implement for Instagram (Graph API `GET /{media-id}/comments`) and a Fake driver for local dev; `SyncCommentsJob` per workspace on the Laravel scheduler (e.g. every 15 min) writing to `comments` (`external_id`, `post_id` dedupe); optional Meta webhooks later.
- **Stack:** Laravel 12 jobs + scheduler (`php artisan schedule:work` / cron), Guzzle, existing `SocialProviderFactory` fake/api driver pattern, MySQL.
- **CC:** all code + tests. **YOU:** Meta App Review for `instagram_manage_comments` permission; cron/supervisor entry on Hetzner. TikTok comment API requires separate portal approval — treat as follow-up.

### T8 — Sentiment analysis service (backend)
- **What:** `SentimentAnalysisService` behind the existing `OpenAiClientInterface` (chat model, JSON mode, batch classify); classify on ingest in `SyncCommentsJob`; fake driver returns deterministic results; backfill command `php artisan comments:classify`.
- **Stack:** Laravel service + existing OpenAI driver pattern, `gpt-4o-mini` (cheap, sufficient for 3-class sentiment), queue.
- **CC:** all code + tests. **YOU:** OpenAI API key/budget (already configured in prod; monitor token costs).

### T9 — AI reply suggestions + reply sending (backend + UI)
- **What:** `POST /comments/{comment}/suggest-reply` (OpenAI, uses business profile tone) and `POST /comments/{comment}/reply` (Instagram Graph API reply; fake driver locally); wire web `/comments` page and mobile Konversationen screen: real suggestion per comment, editable, send button that actually sends; replace hardcoded 68/24/8% with a real `GET /comments/stats` endpoint.
- **Stack:** Laravel + OpenAI driver, Instagram Graph API, Next.js 16 + React Query (web), Expo (mobile).
- **CC:** all code + tests. **YOU:** included in the T7 Meta permission review.

---

## Phase 3 — Facebook integration

### T10 — Facebook OAuth + publishing
- **What:** `FacebookApiProvider` + OAuth controller (`/social-accounts/facebook/connect|disconnect|status|callback`), Facebook Page selection, publishing via Graph API (`/{page-id}/photos`, `/feed`); register in `SocialProviderFactory` (`SOCIAL_FACEBOOK_DRIVER=fake|api`); connection card on web `/social-accounts`; include Facebook in post platform targets.
- **Stack:** Laravel, Meta Graph API v21+, existing OAuthState model, Next.js connection card (reuse `SocialProviderConnectionCard`).
- **CC:** all code + tests. **YOU:** Meta Developer Portal — add Facebook Login product, request `pages_show_list`, `pages_manage_posts`, `pages_read_engagement`; pass App Review; set env keys on server.

---

## Phase 4 — Real analytics

### T11 — Platform insights sync + analytics storage
- **What:** `post_metrics` + `account_metrics` tables (migration); `SyncAnalyticsJob` pulling Instagram Insights (impressions, reach, likes, comments, follower count) per connected account daily; rewrite `AnalyticsController::kpi` to aggregate real data with `is_estimated: false`; keep simulated values only as fake-driver output.
- **Stack:** Laravel migrations/jobs/scheduler, Instagram Graph API Insights, MySQL aggregates.
- **CC:** all code + tests. **YOU:** `instagram_manage_insights` permission via Meta App Review.

### T12 — Wire dashboards to real data
- **What:** Remove every hardcoded metric on mobile (dashboard 1.9K/342/+18.2%, profile Insights block, copilot "3 neue Ideen") and web; render from `/analytics/kpi` (+ a new `/analytics/timeseries` for the chart); show a clear "Beispieldaten" badge wherever the fake driver is active; real chart on mobile profile.
- **Stack:** Mobile: `react-native-svg` or `victory-native` for the chart; Web: existing components; React Query.
- **CC:** all code. **YOU:** nothing.

---

## Phase 5 — Notifications

### T13 — Notification backbone + push
- **What:** `notifications` table (or Laravel's native notifications), events: post published/failed, new comment, negative-sentiment comment, quota near limit; `GET /notifications` + mark-read API; Expo push: store device tokens (`device_tokens` table), send via `expo-server-sdk-php`; mobile registers token, bell icon shows real list.
- **Stack:** Laravel Notifications + queue, `expo-notifications` (add to mobile), `expo-server-sdk-php` (composer).
- **CC:** all code. **YOU:** Expo project push credentials (FCM key for Android, APNs key for iOS — requires Apple Developer account), test on physical devices.

---

## Phase 6 — GDPR completion

### T14 — Data export + account deletion
- **What:** `POST /auth/export-data` (queued job → ZIP of user/workspace/posts/media/generations/comments JSON → signed download link, Art. 20) and `DELETE /auth/account` (soft-grace or immediate cascade, revoke tokens, delete media files, Art. 17); retention policy for ingested comments (e.g. purge after N months, configurable); settings UI on web + mobile; wire the mobile "DSGVO Datenschutz" row to the Datenschutz page (WebView/external link).
- **Stack:** Laravel queued jobs, ZipArchive, signed URLs; Next.js settings section; Expo WebView/Linking.
- **CC:** all code + tests. **YOU:** legal review of the deletion/retention policy and updated Datenschutzerklärung (lawyer), confirm grace-period behavior.

### T15 — Copy alignment (honesty pass)
- **What:** Update mobile onboarding slides and register screen to only promise shipped features (remove Facebook/best-times/sentiment claims until their phases land); remove or implement Apple/Google sign-in buttons (see T17).
- **Stack:** text edits in mobile screens / `de.ts`.
- **CC:** all code. **YOU:** approve wording.

---

## Phase 7 — Later / optional

### T16 — Best posting times
- **What:** v1 heuristic per industry (static lookup by `business_type`, surfaced in scheduling UI); v2 data-driven from `post_metrics` once T11 has weeks of data.
- **Stack:** Laravel service + small config table; no ML infra needed initially.
- **CC:** all code. **YOU:** nothing.

### T17 — Apple / Google sign-in
- **What:** `expo-apple-authentication` + `@react-native-google-signin` (or expo-auth-session), backend `POST /auth/social` verifying identity tokens, link-or-create user.
- **Stack:** Expo auth libs, Laravel Socialite or manual JWT verification.
- **CC:** all code. **YOU:** Apple Developer account ($99/yr, Sign in with Apple capability), Google Cloud OAuth client IDs. Note: Apple requires "Sign in with Apple" if you offer any third-party login on iOS.

### T18 — Caption Optimizer + Suggestions screens
- **What:** Replace the two "Demnächst verfügbar" placeholders: optimizer = `POST /ai/optimize-caption` (rewrite for engagement, uses tone); suggestions = `GET /ai/suggestions` (content ideas from business profile + recent performance).
- **Stack:** Laravel + OpenAI driver, Expo screens, React Query.
- **CC:** all code + tests. **YOU:** OpenAI cost monitoring.

### T19 — Team approvals & automation workflows
- **What:** Approval state machine on posts (`pending_approval` → approve/reject by `owner`/`manager`), then rule-based automations (e.g. auto-reply to positive comments). Design-heavy; spec before build.
- **Stack:** Laravel state machine + policies, web UI.
- **CC:** implementation after a written spec. **YOU:** product decisions (roles, rules, limits per plan).

### T20 — TikTok publishing unblock
- **What:** Code exists; blocked on portal approval. Afterwards: verify scopes, end-to-end publish test, enable video upload from media library.
- **CC:** verification + fixes. **YOU:** TikTok Developer Portal — apply for Content Posting API approval (this is the blocker; only you can do it).

---

## Suggested order & dependency notes

1. **T1–T3** (days, unblocks mobile entirely) → 2. **T4–T6** (mobile core loop) → 3. **T7–T9** (comments: the concept's differentiator; start Meta App Review NOW since it takes weeks and also gates T10/T11) → 4. **T10–T12** → 5. **T13** → 6. **T14–T15** → 7. **T16–T20**.

Your critical-path external dependencies (start early, all waiting time, not work time):
- Meta App Review: `instagram_manage_comments`, `instagram_manage_insights`, Facebook Login + pages permissions (gates T7, T9, T10, T11)
- TikTok Content Posting API approval (gates T20)
- Apple Developer account + push/sign-in credentials (gates T13 iOS, T17)
- Lawyer review of Datenschutz/retention texts (gates T14 go-live)
- Decision: in-app purchase strategy for mobile subscriptions (gates T6 store release)
