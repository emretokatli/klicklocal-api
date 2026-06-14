# Claude Code Prompt — WebAnalyze Code-First Refactor

## Context

The current `/admin/website-analyze` pipeline runs every check through a Claude Agent SDK process
(`AgentSdkWebAnalyzeClient` → `analyze-website.mjs`). The agent browses the site, searches the web,
and writes the report over 12–20 conversation turns on claude-opus — costing ~$0.50 per run and
~90 seconds. That doesn't scale to hundreds of customers.

**Goal:** add a new `code_first` driver that moves all data collection into PHP code (no AI), then
calls Claude once for report synthesis. Target: ~$0.03/run, ~15 s, every step always runs.

The existing `WebAnalyzeClientInterface` / `AgentSdkWebAnalyzeClient` / `FakeWebAnalyzeClient`
pattern is already in place — add a third implementation, wire it up, and keep everything backward-
compatible. Do NOT delete the existing agent driver; it stays as `WEBANALYZE_DRIVER=api`.

---

## Existing files you must understand first

Read these before touching anything:

- `backend/app/Services/Ai/AgentSdkWebAnalyzeClient.php` — current implementation
- `backend/app/Services/Ai/DTOs/WebAnalyzeResultDTO.php` — result shape (keep unchanged)
- `backend/app/Services/Ai/WebAnalyzeService.php` — thin wrapper (keep unchanged)
- `backend/app/Providers/AppServiceProvider.php` — DI wiring
- `backend/config/webanalyze.php` — driver config
- `backend/app/Jobs/RunWebsiteAnalyzeJob.php` — the job that calls `WebAnalyzeService::analyze()`
- `backend/app/Services/Ai/WebsiteAnalysisService.php` — the onboarding HTML fetcher (reuse its
  `fetchPageText`-style pattern but NOT the class itself)

---

## Step 1 — Extend config/webanalyze.php

Add these keys (all env-overridable, sane defaults):

```php
'driver' => env('WEBANALYZE_DRIVER', env('ANTHROPIC_API_KEY') ? 'code_first' : 'fake'),

// Code-first driver settings
'report_model'    => env('WEBANALYZE_REPORT_MODEL', 'claude-haiku-4-5-20251001'),
'serp_api_key'    => env('SERP_API_KEY', ''),
'serp_driver'     => env('SERP_DRIVER', env('SERP_API_KEY') ? 'api' : 'fake'),
'cache_ttl_hours' => (int) env('WEBANALYZE_CACHE_TTL_HOURS', 168),  // 7 days
'cache_driver'    => env('WEBANALYZE_CACHE_DRIVER', 'redis'),        // or 'array' in tests
```

Change the existing default driver from `api` to `code_first` (the agent runner becomes opt-in via
`WEBANALYZE_DRIVER=api`).

---

## Step 2 — WebsiteDataCollector

Create `backend/app/Services/Ai/WebsiteDataCollector.php`.

This class does ALL website checks in PHP with NO AI calls. It returns a plain PHP array (the
"raw data payload") that the report generator will consume.

Implement these checks using `Illuminate\Support\Facades\Http`:

### 2a. Fetch & parse

```php
public function collect(string $url): array
```

- HTTP GET the URL with `User-Agent: KlicklocalBot/2.0 (+https://klicklocal.app)`, timeout 12 s,
  follow up to 3 redirects, fail fast on non-HTML content types (image/*, application/pdf, etc.)
  or bodies >2 MB.
- **SSRF guard (required):** resolve the hostname; reject any response whose final URL resolves to
  a private/reserved IP (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 127.0.0.0/8,
  169.254.0.0/16, ::1, fc00::/7). Re-check after each redirect.
- If the site is down/unreachable, set `site_reachable: false` and continue — it's a finding.

### 2b. Extract from raw HTML (regex/string operations only — no DOM lib required)

Return an array with all of these keys. Set to `null` when not found.

**Meta & SEO:**
```php
'title'              => string|null,    // content of <title>
'title_length'       => int|null,
'meta_description'   => string|null,
'meta_desc_length'   => int|null,
'has_viewport'       => bool,
'lang'               => string|null,    // html lang attribute
'canonical'          => string|null,
'og_title'           => string|null,
'og_image'           => string|null,
'robots_meta'        => string|null,
'h1_count'           => int,            // count of <h1> tags
'h1_texts'           => string[],       // first 3 H1 texts
'img_total'          => int,
'img_missing_alt'    => int,
'https'              => bool,
'has_local_keyword_in_title' => bool,  // city/Stadtbezirk name in title (use business city if known, else guess from address)
```

**CMS / tech stack** (string-search the raw HTML):
```php
'cms' => string[],  // e.g. ['WordPress', 'Elementor'] or ['Wix'] or []
```
Detect: WordPress (wp-content/wp-includes), Wix (wixstatic/wix.com), Jimdo, TYPO3, Joomla,
Shopify (cdn/shop), Squarespace, Webflow, Elementor (elementor), Divi (et_pb_), Next.js (__NEXT_DATA__).

**Tracking & consent** (string-search):
```php
'tracking' => string[],  // e.g. ['Google Tag Manager', 'Meta Pixel', 'Consent: Cookiebot']
```
Detect: GTM (googletagmanager.com/gtm.js), GA4 (gtag(|googletagmanager.com/gtag),
Universal Analytics (google-analytics.com/analytics.js), Meta Pixel (connect.facebook.net),
Google Ads (googleadservices), TikTok Pixel (tiktok.com/i18n/pixel),
Hotjar (static.hotjar.com), Matomo, Cookiebot, Usercentrics, Borlabs, Complianz.

**Schema.org** (JSON-LD):
```php
'schema_types'       => string[],   // e.g. ['Restaurant', 'LocalBusiness']
'has_local_business' => bool,
```
Extract all `<script type="application/ld+json">` blocks, decode JSON, collect all @type values.

**Conversion elements:**
```php
'tel_links'          => string[],   // href values of all a[href^="tel:"]
'mailto_links'       => string[],   // href values of all a[href^="mailto:"]
'has_whatsapp'       => bool,       // wa.me or api.whatsapp.com in HTML
'has_online_booking' => bool,       // termin|buchen|booking|reservier|opentable|resmio in HTML
'has_maps_embed'     => bool,       // google.com/maps or maps.googleapis in HTML
'has_opening_hours'  => bool,       // öffnungszeiten|opening hours in body text
'form_count'         => int,
```

**Placeholder/broken link detection:**
For each tel/mailto link, check if the displayed text (inner text of the `<a>`) matches the href
target. Flag any whose href contains placeholder patterns: domain.com, example, 987654321,
mustermann, your@email. Return:
```php
'broken_contact_links' => [['display' => '...', 'href' => '...'], ...]
```

**Contact data (from Impressum):**
Look for a link whose text or href matches `/impressum|kontakt|imprint/i`. If found, fetch that
page (same SSRF guard, same timeout). Extract:
```php
'emails'    => string[],
'phones'    => string[],
'addresses' => string[],
'vat_ids'   => string[],    // DE\d{9}
```

**Social links (filter platform placeholders immediately):**
```php
'social_links' => string[],  // real profile URLs only
```
Accept links to: facebook.com, instagram.com, linkedin.com, youtube.com, tiktok.com, x.com,
twitter.com, xing.com.
**Reject** any URL that contains `/wix`, `/jimdo`, `/squarespace`, `/webflow` after the domain —
these are builder defaults. Flag rejected ones in:
```php
'placeholder_social_links' => string[],
```

---

## Step 3 — SerpApiSearchClient

Create `backend/app/Services/Ai/SerpApiSearchClient.php` and its contract
`backend/app/Services/Ai/Contracts/SerpSearchClientInterface.php`.

```php
interface SerpSearchClientInterface
{
    /** Returns top organic results for a query */
    public function search(string $query): array;  // ['results' => [...], 'raw' => [...]]
}
```

**Real implementation** (`SerpApiSearchClient`):
- Call `https://serpapi.com/search?q={query}&location=Germany&hl=de&gl=de&api_key={key}&engine=google`.
- Timeout 10 s. Return the top 5 organic results as `[['title', 'url', 'snippet']]` plus the
  `local_results` array (Google Maps Pack) as-is.
- On failure, return `['results' => [], 'raw' => [], 'error' => '...']` — never throw; the report
  generator handles missing data gracefully.

**Fake implementation** (`FakeSerpSearchClient`):
- Returns a hardcoded payload with 3 competitor entries that look realistic (different business
  names, ratings 4.2–4.7, review counts 80–350). Used when `SERP_DRIVER=fake`.

**Bind in `AppServiceProvider`** based on `config('webanalyze.serp_driver')`.

---

## Step 4 — SocialProfileFetcher

Create `backend/app/Services/Ai/SocialProfileFetcher.php`.

```php
public function fetchInstagram(string $handle): array
```

- HTTP GET `https://www.instagram.com/{handle}/` with a realistic browser UA, timeout 8 s.
- Extract from raw HTML (Instagram embeds this in the page even without JS):
  - Follower count: look for `"edge_followed_by":{"count":(\d+)}` in the HTML.
  - Post count: `"edge_owner_to_timeline_media":{"count":(\d+)}`.
  - Last post timestamp: first `"taken_at_timestamp":(\d+)` value → convert to date.
- Return:
```php
[
    'handle'      => string,
    'exists'      => bool,
    'followers'   => int|null,
    'post_count'  => int|null,
    'last_post'   => string|null,  // ISO date or null
    'posts_per_week' => float|null, // rough estimate from post_count / account_age_weeks
    'error'       => string|null,
]
```
- On any failure (login wall, block, timeout), set `exists: true` (the profile URL exists), all
  numeric fields null, `error: 'could not scrape'`. Never throw.

**Also implement** `fetchFacebook(string $url): array` with the same output shape, scraping
`og:description` and post timestamps from the open-graph meta tags (public FB pages embed these).

---

## Step 5 — WebAnalyzeReportGenerator

Create `backend/app/Services/Ai/WebAnalyzeReportGenerator.php`.

This makes a **single** `POST /messages` call to the Anthropic API (using the existing HTTP pattern
from `WebsiteAnalysisService`). It takes the structured payload from steps 2–4 and returns the
full markdown report string.

### 5a. Build the structured input

```php
private function buildUserMessage(array $payload): string
```

Serialize the `$payload` array (all keys from steps 2–4 + SerpAPI results + social data) as a
compact JSON block. Prefix it with a one-line instruction. Keep it under 4 000 tokens — the data
should easily fit; strip any raw HTML that crept in.

### 5b. System prompt

The system prompt instructs the model to:

1. Read the structured JSON and produce a complete lead-analysis report in German.
2. Use the **exact** v2 template from the SKILL (8-category rubric, all sections including
   Social-Media-Audit, Google-Sichtbarkeit & Wettbewerb, So hilft Klicklocal, Interne Notizen).
3. Score using the v2 rubric:
   Technik 10 · SEO 20 · Content 10 · Conversion 20 · Social 15 · Google-Sichtbarkeit 10 ·
   Marketing 10 · Eigenständigkeit 5.
4. In "So hilft Klicklocal" map only real Klicklocal capabilities: Instagram/TikTok/Facebook
   post scheduling, KI-content generation, Reel-Studio (15-s Reels), post calendar.
5. The "Interne Notizen" block (after `--- Interne Notizen ---`) must list: any fields that were
   null/unverifiable and why, and the scoring judgment calls. This block must NOT appear in the
   PDF export.
6. The Annahmen line under Wachstumsprognose is mandatory. Use the industry benchmark for the
   detected business type (Gastro ~18–22 €, Friseur ~40–70 €, Handwerk ~500–3 000 €) labeled as
   "Branchenwert (Annahme)".
7. Every competitor claim must use the name from the SerpAPI data — never a generic placeholder.
8. No model name, cost, or duration anywhere in the customer-facing sections.

### 5c. API call

```php
$response = Http::withToken($this->apiKey)
    ->timeout(60)
    ->acceptJson()
    ->post('https://api.anthropic.com/v1/messages', [
        'model'      => $this->model,   // from config('webanalyze.report_model')
        'max_tokens' => 4096,
        'system'     => $systemPrompt,
        'messages'   => [
            ['role' => 'user', 'content' => $userMessage],
        ],
    ]);
```

Parse the response, extract `content[0].text`, validate it contains `# Lead-Analyse`,
return the string. Track token usage from `usage.input_tokens + usage.output_tokens` and compute
approximate cost using hardcoded per-model rates (haiku: $0.00025/$0.00125 per 1k in/out;
sonnet: $0.003/$0.015) for the `total_cost_usd` field.

---

## Step 6 — CodeFirstWebAnalyzeClient

Create `backend/app/Services/Ai/CodeFirstWebAnalyzeClient.php` implementing
`WebAnalyzeClientInterface`.

```php
public function analyze(string $website): WebAnalyzeResultDTO
```

Orchestrate steps 2–5 with Redis caching:

```php
$cacheKey = 'webanalyze:v2:' . md5(strtolower(trim($website)));
$ttl = now()->addHours(config('webanalyze.cache_ttl_hours', 168));

return Cache::driver(config('webanalyze.cache_driver', 'redis'))
    ->remember($cacheKey, $ttl, function () use ($website) {
        return $this->runFresh($website);
    });
```

`runFresh()`:
1. `$siteData = $this->dataCollector->collect($website)`
2. `$serpData = $this->searchClient->search("<business type> <city from site data>")`
   — also run `$this->searchClient->search('"<business name>" <city>')` for GBP check.
3. For each real social profile found in `$siteData['social_links']`, extract the handle and call
   `$this->socialFetcher->fetchInstagram($handle)` or `fetchFacebook($url)`.
4. Merge everything into one `$payload` array and call `$this->generator->generate($payload)`.
5. Parse score/band from the returned markdown using the existing `WebAnalyzeReportParser::parseScore()`.
6. Return a `WebAnalyzeResultDTO` with `numTurns: 1`, `model: config('webanalyze.report_model')`,
   and the computed `totalCostUsd`.

Inject `WebsiteDataCollector`, `SerpSearchClientInterface`, `SocialProfileFetcher`, and
`WebAnalyzeReportGenerator` via constructor.

---

## Step 7 — Wire up in AppServiceProvider

In `AppServiceProvider::register()`, add the `code_first` branch to the existing
`WebAnalyzeClientInterface` binding:

```php
$this->app->bind(WebAnalyzeClientInterface::class, function ($app): WebAnalyzeClientInterface {
    $config = $app['config']->get('webanalyze');
    $driver = $config['driver'] ?? 'fake';

    if ($driver === 'fake') {
        return new FakeWebAnalyzeClient;
    }

    if ($driver === 'code_first') {
        return new CodeFirstWebAnalyzeClient(
            dataCollector: $app->make(WebsiteDataCollector::class),
            searchClient:  $app->make(SerpSearchClientInterface::class),
            socialFetcher: $app->make(SocialProfileFetcher::class),
            generator:     $app->make(WebAnalyzeReportGenerator::class),
        );
    }

    // driver === 'api' — existing agent runner
    return new AgentSdkWebAnalyzeClient(/* ...existing args... */);
});
```

Also bind `SerpSearchClientInterface` to either `SerpApiSearchClient` or `FakeSerpSearchClient`
based on `config('webanalyze.serp_driver')`.

Bind `WebAnalyzeReportGenerator` as a singleton, injecting `ANTHROPIC_API_KEY` and
`config('webanalyze.report_model')`.

---

## Step 8 — WebAnalyzeResultDTO: add cached flag

Add `public bool $cached = false` as a named constructor argument with default `false`.
Add `'cached' => $this->cached` to `toArray()`. The `CodeFirstWebAnalyzeClient` sets it to `true`
when returning from cache (wrap the `Cache::remember` result and set the flag afterward).

---

## Step 9 — Update frontend to show cached badge

In `frontend/src/lib/web-analyze/types.ts` add `cached?: boolean` to `WebAnalyzeRunResult`.
In `frontend/src/components/admin/website-analyze/WebAnalyzeReport.tsx`, show a small "Gecacht"
badge (use `de.admin.websiteAnalyze.report.cached`) if `result.cached === true`.
Add the German string to `frontend/src/lib/i18n/de.ts`:
`cached: 'Gecacht – Analyse aus dem Zwischenspeicher (bis zu 7 Tage alt)'`

---

## Step 10 — Tests

### Unit: WebsiteDataCollector
`backend/tests/Unit/WebsiteDataCollectorTest.php`

Use `Http::fake()` to mock HTTP responses. Test:
- Happy path: title/meta/tracking/schema.org extracted correctly.
- Site down: returns `site_reachable: false`, other fields null/empty.
- Placeholder social links rejected into `placeholder_social_links`.
- Broken tel link detected in `broken_contact_links`.
- SSRF: private IP in redirect rejected (mock a 302 to `http://192.168.1.1`).

### Unit: SocialProfileFetcher
`backend/tests/Unit/SocialProfileFetcherTest.php`

Use `Http::fake()` with a stub Instagram HTML containing the known regex patterns.
Test follower count, post count, last post date extracted correctly.
Test graceful fallback when login-wall HTML contains none of the patterns.

### Feature: CodeFirstWebAnalyzeClient (integration)
`backend/tests/Feature/CodeFirstWebAnalyzeClientTest.php`

Bind `SerpSearchClientInterface` → `FakeSerpSearchClient` in the test.
Mock `Http::fake()` for the site fetch and Instagram fetch.
Mock the Anthropic `/v1/messages` call to return a minimal valid v2 report markdown.
Assert:
- `WebAnalyzeResultDTO::$reportMarkdown` contains `# Lead-Analyse`.
- `WebAnalyzeResultDTO::$totalCostUsd` > 0.
- Second call with the same URL returns immediately (from cache); Anthropic was called exactly once.
- Cache can be cleared with `Cache::forget($cacheKey)`.

---

## Step 11 — Environment docs

Append to `deploy/README.md` under "Environment variables":

```
# WebAnalyze code-first driver (recommended)
WEBANALYZE_DRIVER=code_first
WEBANALYZE_REPORT_MODEL=claude-haiku-4-5-20251001   # or claude-sonnet-4-6 for higher quality
SERP_API_KEY=<your SerpAPI key>                      # https://serpapi.com — ~$50/month for 5000 searches
SERP_DRIVER=api                                      # set to 'fake' for local dev without a key
WEBANALYZE_CACHE_TTL_HOURS=168                       # 7 days; set to 0 to disable caching
WEBANALYZE_CACHE_DRIVER=redis                        # must match CACHE_DRIVER or a named store

# Old agent-based driver (still available, opt-in)
# WEBANALYZE_DRIVER=api
# WEBANALYZE_MAX_TURNS=20
# WEBANALYZE_MAX_BUDGET_USD=1.25
```

---

## Acceptance checklist

- [ ] `WEBANALYZE_DRIVER=code_first` is the new default when `ANTHROPIC_API_KEY` is set
- [ ] `AgentSdkWebAnalyzeClient` and `WEBANALYZE_DRIVER=api` still work unchanged
- [ ] `WebsiteDataCollector` extracts all fields listed in step 2 from a real page fetch
- [ ] SSRF guard rejects private IPs and redirect-to-private
- [ ] SerpAPI called with business-type + city query (not just the raw URL)
- [ ] Instagram follower/post/last-post extracted from public page HTML
- [ ] Single Anthropic Messages API call produces a v2 template report
- [ ] Report contains all 8 category scores, Social-Media-Audit, Google-Sichtbarkeit,
      So hilft Klicklocal, and Interne Notizen sections
- [ ] Redis cache prevents a second Anthropic call for the same URL within TTL
- [ ] `cached: true` flag in DTO and visible in frontend
- [ ] All unit + feature tests pass: `php artisan test`
- [ ] Frontend build passes: `cd frontend && npm run build`
- [ ] Cost per run ≤ $0.05 when using `claude-haiku-4-5-20251001` (verify via `total_cost_usd`)
