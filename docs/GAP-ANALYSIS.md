# Klicklocal — Concept vs. Code Gap Analysis

Date: 2026-06-10. Sources: project concept (AI business assistant with automated content creation, scheduling, community engagement, sentiment analysis, comment suggestions across Instagram/TikTok/Facebook, GDPR-compliant), `PROJECT.md`, `docs/FIRST-POST-MVP.md`, `CLAUDE.md`, and the current `backend/`, `frontend/`, `mobile/` code.

---

## Part 1 — Concept features missing or only stubbed in code

### 1.1 Sentiment analysis — NOT implemented (only stored)
- `comments.sentiment` is a plain string column (`positive|neutral|negative`) on the `Comment` model. It is set **manually by the API caller** in `CommentController::store()` and defaults to `'neutral'`.
- There is **no analysis anywhere**: no NLP/OpenAI call classifies comment text, no job re-scores comments. The `OpenAiClientInterface` abstraction exists and could be reused, but no `SentimentAnalysisService` exists.
- The mobile Konversationen screen shows a sentiment summary of **hardcoded values** (`68% / 24% / 8%`) regardless of actual data.

### 1.2 Automated comment suggestions / replies — NOT implemented
- No backend endpoint generates a reply suggestion, and no endpoint sends a reply to any platform. The only comment routes are `GET /comments` and `POST /comments` (local create).
- Mobile shows a "KI-ANTWORT" card with a **hardcoded German string** ("Danke fur dein Feedback! …"), only on the first comment; both "Senden" and "Bearbeiten" just navigate to a placeholder detail screen (`comments/[commentId].tsx` renders only the ID).
- Web `/comments` page is a read-only list with filters — no reply, no suggestion.

### 1.3 Community engagement / comment ingestion — NOT implemented
- Comments are never fetched from Instagram/TikTok/Facebook. There is no sync job, webhook, or provider method for comments; `Comment.external_id` and `post_id` exist but nothing populates them.
- In practice the comments inbox is empty unless something POSTs comments into the API manually. The mobile empty state ("Verbinde Instagram oder TikTok um Kommentare zu sehen") implies connecting accounts will surface comments — it won't.

### 1.4 Facebook — settings only, no real integration
- Only `FakeFacebookProvider` + admin `FacebookProviderSettingsService` exist. There is **no real Facebook API provider, no OAuth connect/disconnect/status routes, and no customer UI** (web `/social-accounts` covers Instagram + TikTok only).
- Mobile onboarding slide 2 explicitly promises "automatische Verteilung uber Instagram, TikTok und Facebook" — Facebook publishing is impossible today.
- LinkedIn: API provider classes exist but there are no customer routes either.

### 1.5 Analytics — simulated, not real
- `GET /analytics/kpi` fabricates numbers: `impressions = published_posts × 420`, `reach = × 310`, engagement fixed at `5.8` (`is_estimated: true`). No platform insights are fetched, and there are no dedicated analytics tables (confirmed as missing in `PROJECT.md`).
- Mobile dashboard/profile additionally hardcode Likes `1.9K`, Comments `342`, `+18.2%`, Reach `128.4K`, `+842 Follower`, etc. (see Part 2).

### 1.6 Notifications / push — absent
- No `notifications` table, model, or API (acknowledged in `PROJECT.md`). Mobile has no `expo-notifications` dependency; the dashboard bell icon is decorative. "Push notification UX" is listed as a mobile responsibility but nothing exists.

### 1.7 Best posting times / AI scheduling intelligence — absent
- Promised in mobile onboarding ("Beste Posting-Zeiten") and implied by the concept's automation claims. No service computes posting times; scheduling is purely user-supplied `scheduled_at`.

### 1.8 Automation workflows & team approvals — absent
- Both listed as concept/roadmap items in `PROJECT.md`; no code exists.

### 1.9 GDPR compliance — partial
- Present: German legal pages on web (`/agb`, `/datenschutz`, `/impressum`, `/widerruf`), EU-hosted server (Hetzner), consent text on registration.
- Missing: data export (Art. 20) and account-deletion endpoints for users (workspace delete exists, user self-deletion does not); mobile's "DSGVO Datenschutz" row is a **no-op** (`onPress={() => undefined}`); no in-app privacy policy view; comments store third-party personal data (author handles, text) with no retention/erasure mechanism.

### 1.10 Smaller gaps
- Mobile "Suggestions", "Caption Optimizer", "Planen" (schedule), "Kalender", and "Abonnement" screens are all "Demnachst verfugbar" placeholders.
- TikTok publishing depends on unapproved Content Posting API scopes (known issue in `CLAUDE.md`); production-ready publishing beyond Instagram remains future work per `PROJECT.md`.

---

## Part 2 — Mobile app workflow vs. documented story: discrepancies

The documented story (web): `/register` email-only → 12-step onboarding wizard (name, business, website, KYC, AI website analysis, AI-prefilled description/audience/UVP, goal, password) → `/ai`. Core loop: *describe business → upload photo → AI generates on-brand post → schedule*.

### 2.1 Registration flow diverges — and is currently broken (bug)
- Mobile `register.tsx` collects business name + email + password on **one screen**, then `auth-provider.tsx` calls `POST /auth/register-email` followed immediately by `POST /auth/onboarding/complete` with hardcoded `industry: 'Sonstiges'` and `first_name: ''`.
- **Bug:** `CompleteOnboardingRequest` requires `first_name` (`required|string|max:255`); an empty string fails Laravel's `required` rule, so **mobile registration always returns 422**. (`src/features/auth/api.ts` has a parallel register that doesn't pass `first_name` at all — same failure.)
- Even if fixed, mobile skips the entire documented wizard: no KYC, no website URL, no AI website analysis, no AI-prefilled description/target audience/UVP, no primary goal. The business profile ends up nearly empty, which directly weakens the AI generation quality the whole concept depends on.

### 2.2 Login ignores onboarding state
- Web: login redirects to `/onboarding` when `onboarding_completed` is null; dashboard is gated (`OnboardingGate`).
- Mobile: `login()` receives `onboarding_completed` from the API but **discards it** and routes to the dashboard. A half-onboarded user (no workspace, no password-completed profile) lands on the dashboard with `workspaceId` falling back to `0`, so every workspace-scoped call (`/analytics/kpi`, `/comments`, `/posts`) fails validation.

### 2.3 Token storage violates the documented architecture
- `PROJECT.md`: "Mobile stores the token through Expo Secure Store." `expo-secure-store` is installed, but `auth-provider.tsx` persists the full session (including bearer token) in **AsyncStorage** — unencrypted, contrary to both the docs and the GDPR/data-security positioning.

### 2.4 The core loop cannot be completed on mobile
Story: describe → upload photo → generate → schedule. On mobile:
- The "Beschreibe deine Idee" field is **static display text**, not an input; `generateContent` is called with an empty prompt.
- No media upload exists (no image picker, no `POST /media/upload` usage); the preview image is a colored placeholder box.
- The generated caption is displayed but **never saved as a draft post** — there is no `POST /posts` call anywhere in mobile.
- "Schedule" and "Open Calendar" lead to "Demnachst verfugbar" placeholders.
Net effect: mobile can generate a caption (consuming AI quota) but cannot create, schedule, or publish anything — contradicting onboarding slide 2 ("Plane in Sekunden") and the concept's mobile-first automation claim. `PROJECT.md` does flag mobile posts/scheduling as roadmap, but the UI presents the features as present.

### 2.5 Hardcoded data presented as live metrics
- Dashboard: Likes `1.9K`, Comments `342`, week trend `+18.2%`, and the "3 neue Ideen warten auf dich" copilot card (no ideas exist; it just opens the content tab). Greeting is always "Guten Morgen".
- Plan screen (`ai/index.tsx`): week strip hardcoded to "MO 27 … SO 2 / JUNI 2026"; post times hardcoded to `09:00 / 13:30 / 18:42` by list index, ignoring each post's real `scheduled_at`; every post is labeled as today.
- Profile "Insights": all eight metrics (128.4K reach, +24.6%, +842 followers, 6.4% ER, 9.1K visits, 3.2% story exits) are static; the chart is an empty gray box.
- Comments: sentiment summary percentages hardcoded (see 1.1).
This is the largest story/code discrepancy: the app *looks* like the concept (analytics, copilot, sentiment inbox) while only posts list, one KPI call (itself simulated), social status, comments list, and AI generate are real API calls.

### 2.6 Onboarding slides promise unbuilt features
Slide 2: best posting times + Facebook distribution (neither exists). Slide 3: "Sentiment-Analyse, smarte Antwortvorschlage und ein Inbox fur alle Kanale" — none implemented (Part 1.1–1.3).

### 2.7 Dead interactive elements
- "Weiter mit Apple" / "Weiter mit Google" buttons are no-ops.
- "DSGVO Datenschutz" row is a no-op.
- Dashboard notification bell is non-interactive.
- Comment "Senden" does not send a reply.

### 2.8 Subscription gating not handled
- `POST /ai/generate` requires `subscription.required` + `feature.quota:ai_generation` (402 without active/trialing subscription). Mobile has no subscription/upgrade UI (placeholder screen) and no 402 handling — a user whose trial lapsed gets a generic error with no path to resolve it in-app.

### 2.9 Social connect deferred to web
- Mobile correctly avoids provider logic, but offers no in-app OAuth (web view/deep link) — dashboard says "Verbinde Instagram oder TikTok im Web-Dashboard." Acceptable architecturally, but diverges from the concept of a self-contained mobile assistant. The social status check also fabricates account IDs (`id: results.length + 1`) client-side.

---

## Part 3 — Priority recommendations

1. **Fix mobile registration 422** (send a real `first_name` or relax the rule) — registration is currently dead on mobile.
2. **Respect `onboarding_completed` on mobile login** and handle the no-workspace case.
3. **Move mobile session storage to Expo Secure Store** (already a dependency).
4. Either **remove/flag hardcoded metrics** ("Beispieldaten" badge) or wire them to `/analytics/kpi` — current state misleads users and risks app-store rejection for fake functionality claims.
5. Build the comment pipeline in the backend (ingest → AI sentiment classification → AI reply suggestion → reply endpoint) before exposing the Konversationen UI, or mark the tab as preview.
6. Complete the mobile core loop: prompt input, image upload, save generation as draft (`POST /posts`), schedule (`POST /posts/{id}/schedule`).
7. Align marketing copy (mobile onboarding slides, register promises) with shipped features — drop Facebook/best-times/sentiment claims until built.
8. Add GDPR data-export and account-deletion endpoints; wire the mobile Datenschutz row to the privacy policy.
