# Claude Code Prompt — WebAnalyze v2 rollout + backend/frontend fixes

Copy everything below the line into Claude Code (run from the monorepo root `D:\NEWxampp\htdocs\klicklocal`).

---

Implement the following improvements to the Website-Analyze (lead analysis) feature and related code. Work through them in order; run `php artisan test` and `npm run build` (in `frontend/`) before finishing.

## 1. Roll out the v2 skill

Replace the contents of `.claude/skills/klicklocal-webanalyze/SKILL.md` with the new version in `docs/skill-drafts/klicklocal-webanalyze-SKILL-v2.md`. Key changes in v2: mandatory social media audit (step 8), mandatory Google Business Profile / competitor check (step 9), new 8-category scoring rubric (Technik 10, SEO 20, Content 10, Conversion 20, Social-Media-Aktivität 15, Google-Sichtbarkeit 10, Marketing-Reife 10, Eigenständigkeit 5), new report sections "Social-Media-Audit", "Google-Sichtbarkeit & Wettbewerb", "So hilft Klicklocal", and a separate "Interne Notizen" block that must never reach the customer-facing output.

## 2. Fix the agent runner so market research is never skipped

In `backend/agent-sdk/analyze-website.mjs` the prompt currently says: "If near the turn/budget limit, skip step 9 web search and mark market data as 'nicht verifiziert'." This caused real reports to ship without any competitor/market data — which is the core of our pitch. Change it:

- Remove the "skip step 9" instruction. Replace with: "Steps 8, 9 and 11a are mandatory. If near the limit, run their minimum versions (one web search each) instead of skipping."
- In `backend/config/webanalyze.php`, raise the defaults: `WEBANALYZE_MAX_TURNS` 12 → 20 and `WEBANALYZE_MAX_BUDGET_USD` 0.5 → 1.25 (both reports ran right up against $0.50, which is what triggered the skip). Keep them env-overridable.
- Pass the maxTurns/maxBudget values into the prompt text as before so the agent knows its real limits.

## 3. Update the frontend report parser and PDF export for the v2 template

- `frontend/src/lib/web-analyze/parse-report.ts`: add SECTION_KEYS and parsed fields for the new sections — `socialAudit` (/^Social-Media-Audit/i), `googleVisibility` (/^Google-Sichtbarkeit/i), `klicklocalPitch` (/^So hilft Klicklocal/i), and `internalNotes` (match the `--- Interne Notizen` block, which may appear after the report body rather than as a `##` heading). Keep backward compatibility: v1 reports without these sections must still parse (fields empty/null).
- `frontend/src/lib/web-analyze/types.ts`: extend `ParsedWebAnalyzeReport` accordingly.
- `frontend/src/components/admin/website-analyze/WebAnalyzeReport.tsx`: render the new sections. Show "Interne Notizen" in a visually distinct collapsed panel marked "Nicht für den Kunden".
- `frontend/src/lib/web-analyze/export-report-pdf.ts`: the exported PDF is customer-facing. Exclude from the PDF: the "Interne Notizen" block AND the run-metadata footer (model / duration / cost) that currently leaks into the PDF. Add an "Internes PDF" secondary export option that includes both, if straightforward; otherwise just strip them from the default export.
- The category-score table now has 8 rows with new max values — make sure the score bars use each category's own max (parse "x/15", "x/10" etc. rather than hardcoded maxima).
- Add the new German strings to `frontend/src/lib/i18n/de.ts` under `de.admin.websiteAnalyze.*`.

## 4. Fix SSRF exposure in WebsiteAnalysisService (security)

`backend/app/Services/Ai/WebsiteAnalysisService.php` `fetchPageText()` fetches arbitrary user-supplied URLs from a **public** endpoint (`POST /onboarding/analyze-website`). Harden it:

- Only allow `http`/`https` and default ports.
- Resolve the hostname and reject private/reserved/loopback/link-local ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 127.0.0.0/8, 169.254.0.0/16, ::1, fc00::/7, etc.). Re-validate after redirects (limit to max 3 redirects), since a public host can 302 to an internal IP.
- Reject responses larger than ~2 MB and non-HTML content types.
- Extract this into a small invokable/helper (e.g. `App\Support\SafeUrlFetcher`) with unit tests covering: private IP rejected, redirect-to-private rejected, oversized body truncated/rejected, happy path.

Also tighten `backend/app/Http/Requests/Onboarding/AnalyzeWebsiteRequest.php`: `website` is currently only `string|max:500` — add a sensible URL/domain format validation rule that accepts inputs without scheme (we prepend https://) but rejects garbage.

## 5. Handle stuck analyze runs

`backend/app/Jobs/RunWebsiteAnalyzeJob.php` has `tries = 1` and timeouts up to ~16 minutes. If the worker dies mid-run, the `WebsiteAnalyzeRun` stays in `processing` forever and the admin UI spins indefinitely. Add:

- A `failed(Throwable $e)` method on the job that marks the run failed.
- A scheduled command (e.g. `webanalyze:expire-stale`) that marks runs stuck in `pending`/`processing` longer than `config('webanalyze.timeout') + 5 min` as failed with message "Zeitüberschreitung". Register it in the scheduler.
- A feature test for the stale-run expiry.

## 6. Minor cleanups (do last, skip if risky)

- The two service names `App\Services\Ai\WebsiteAnalysisService` (onboarding text analysis) and the WebAnalyze lead-report pipeline are easy to confuse. Add a one-line class-level docblock to each clarifying its purpose (do NOT rename — too many touchpoints).
- In `analyze-website.mjs`, `fake-gpt-5` style placeholder model names appear in fake-driver output paths elsewhere; leave functionality but make sure no placeholder/model metadata can end up in the customer PDF (covered by item 3).

## Acceptance checklist

- [ ] v2 SKILL.md in place at `.claude/skills/klicklocal-webanalyze/SKILL.md`
- [ ] Agent prompt no longer permits skipping market research; budget/turn defaults raised
- [ ] Frontend parses + renders all v2 sections; v1 reports still parse
- [ ] Customer PDF contains no model/cost/duration metadata and no Interne Notizen
- [ ] SSRF protections in place with unit tests
- [ ] Stale runs auto-expire; test passes
- [ ] `php artisan test` green, `npm run build` succeeds
