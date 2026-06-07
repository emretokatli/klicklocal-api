# Klicklocal — Shortest Path to First Paying Customer

This feature set delivers the single critical loop: **sign up → describe your business → upload a photo → generate an on-brand Instagram post with AI → schedule it.**

Out of scope by design (not built): billing, analytics, notifications, multi-channel, team approvals.

---

## 1. What was built

### A. Business Profile System

One business profile per workspace, used to personalize every AI generation.

Fields: `business_name`, `business_type`, `city`, `description`, `tone_of_voice`, `products_services`.

### B. AI Content Generator (OpenAI GPT-5)

Input: business profile + optional uploaded image + optional user prompt.
Output (structured): **Instagram Caption**, **Story Text**, **Hashtags**, **Call To Action**.

Every generation is persisted to `ai_generations` (generation history).

### C. Onboarding Wizard

4 steps: **Create Business → Connect Instagram (skippable) → Generate First Post → Schedule First Post.**

---

## 2. Architecture (follows existing conventions)

- Backend owns all logic; controllers stay thin and delegate to services (`PROJECT.md` rules).
- OpenAI is wired through an abstraction (`OpenAiClientInterface`) with a real (`OpenAiClient`) and a deterministic local (`FakeOpenAiClient`) implementation, mirroring the existing social-provider fake/real pattern. The binding is chosen in `AppServiceProvider` from `config/services.php`.
- Access control reuses `WorkspaceService::findForUser` (membership) + `AuthorizationService::hasWorkspacePermission`.
- Frontend uses service modules + React Query hooks + reusable UI components.

---

## 3. Database migrations

| Migration | Change |
|---|---|
| `2026_06_05_000001_create_business_profiles_table` | `business_profiles` (1:1 with `workspaces`) |
| `2026_06_05_000002_create_ai_generations_table` | `ai_generations` (history: caption, story_text, hashtags JSON, call_to_action, model, tokens_used, raw_response JSON, optional `media_id`) |
| `2026_06_05_000003_add_onboarding_to_workspaces_table` | `workspaces.onboarding_step`, `workspaces.onboarding_completed_at` |

Run: `php artisan migrate`

---

## 4. API endpoints (all under `/api/v1`, auth: Sanctum + `customer`)

```txt
GET    /workspaces/{workspace}/business-profile     # show profile (nullable)
PUT    /workspaces/{workspace}/business-profile     # create/update profile
PATCH  /workspaces/{workspace}/onboarding           # { step?, completed? }

POST   /ai/generate                                 # { workspace_id, media_id?, prompt? }
GET    /ai/generations?workspace_id=                # generation history
```

`POST /ai/generate` returns `{ generation: { caption, story_text, hashtags[], call_to_action, ... } }`.
Reuses existing `POST /media/upload` for the image and `POST /posts` + `POST /posts/{id}/schedule` to turn a generation into a scheduled post.

### Validation
- `UpdateBusinessProfileRequest`: `business_name`, `business_type`, `city` required; rest nullable with length caps.
- `GenerateContentRequest`: `workspace_id` required+exists; `media_id` nullable+exists; `prompt` nullable max 1000.
- `UpdateOnboardingRequest`: `step` 1–4, `completed` boolean.
- Service-level guard: generation is blocked until the business profile is complete.

---

## 5. Backend files

```txt
app/Models/BusinessProfile.php
app/Models/AiGeneration.php
app/Models/Workspace.php                                  (+ onboarding fields & relations)

app/Services/Business/BusinessProfileService.php
app/Services/Workspace/OnboardingService.php
app/Services/Ai/Contracts/OpenAiClientInterface.php
app/Services/Ai/OpenAiClient.php                          (real OpenAI chat/completions, JSON mode, vision)
app/Services/Ai/FakeOpenAiClient.php                      (no-key local fallback)
app/Services/Ai/AiContentGenerationService.php            (prompt build + persist + usage record)
app/Services/Ai/DTOs/GeneratedContentDTO.php

app/Http/Controllers/Api/V1/BusinessProfileController.php
app/Http/Controllers/Api/V1/AiContentController.php
app/Http/Controllers/Api/V1/OnboardingController.php
app/Http/Requests/BusinessProfile/UpdateBusinessProfileRequest.php
app/Http/Requests/Ai/GenerateContentRequest.php
app/Http/Requests/Onboarding/UpdateOnboardingRequest.php

config/services.php                                       (+ openai)
app/Providers/AppServiceProvider.php                      (+ OpenAI binding)
routes/api.php                                            (+ routes)
```

---

## 6. Frontend files

```txt
src/types/api.ts                                          (+ BusinessProfile, AiGeneration, onboarding fields)
src/services/business-profile.service.ts
src/services/ai.service.ts
src/services/onboarding.service.ts
src/hooks/use-business-profile.ts
src/hooks/use-ai.ts
src/components/business/BusinessProfileForm.tsx
src/components/ai/AiGeneratorPanel.tsx
src/components/ai/GeneratedContentCard.tsx
src/components/onboarding/OnboardingStepper.tsx
src/app/(dashboard)/ai/page.tsx                           (KI-Studio: generate + history)
src/app/(dashboard)/onboarding/page.tsx                   (4-step wizard)
src/components/layout/Sidebar.tsx                         (+ KI-Studio nav)
src/store/auth-context.tsx                                (register → /onboarding)
src/lib/i18n/de.ts                                        (German copy)
```

---

## 7. Configuration

Add to `backend/.env` (defaults to a local fake driver so the full flow works without a key):

```env
OPENAI_DRIVER=fake          # set to `api` for real generations
OPENAI_API_KEY=             # your OpenAI key (auto-enables `api` driver when set)
OPENAI_MODEL=gpt-5
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_TIMEOUT=60
```

> Note: for image-aware generations in production, the uploaded media URL must be publicly reachable by OpenAI (`APP_URL` / storage URL must be public). The fake driver ignores this.

---

## 8. End-to-end flow

1. Register → redirected to `/onboarding`.
2. **Step 1** creates the workspace (named after the business) + saves the business profile.
3. **Step 2** connects Instagram (OAuth) or is skipped.
4. **Step 3** uploads an image (optional), enters an idea (optional), generates content, and creates a draft post from it.
5. **Step 4** schedules the post (or finishes). `onboarding_completed_at` is set.

The standalone **KI-Studio** page lets users generate content anytime and reuse past generations.

---

## 9. Verification performed

- `php artisan migrate` — 3 migrations applied.
- `php -l` on all new PHP files — no syntax errors.
- `php artisan route:list` — new routes registered.
- Tinker smoke test — OpenAI abstraction resolves and the fake client returns structured caption/story/hashtags/CTA.
- `php artisan test` — 27 passed (no regressions).
- Frontend `tsc --noEmit` clean; ESLint clean on all new files.
