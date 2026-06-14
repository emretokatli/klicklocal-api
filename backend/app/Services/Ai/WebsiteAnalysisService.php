<?php

namespace App\Services\Ai;

use App\Services\Ai\DTOs\BusinessWebsiteAnalysisDTO;
use App\Services\Ai\DTOs\WebsiteAnalysisDTO;
use App\Support\SafeUrlFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Onboarding website analysis: fetches a site's text and asks OpenAI for short
 * German business-profile fields (description, audience, USP). Not to be
 * confused with the WebAnalyze lead-report pipeline (WebAnalyzeService).
 */
class WebsiteAnalysisService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $driver,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly SafeUrlFetcher $urlFetcher,
    ) {}

    /**
     * @param  array{
     *     website: string,
     *     business_name?: string|null,
     *     industry?: string|null
     * }  $input
     */
    public function analyze(array $input): WebsiteAnalysisDTO
    {
        if ($this->apiKey === '' || $this->driver === 'fake') {
            return $this->fakeAnalysis($input);
        }

        $website = $this->normalizeUrl($input['website']);
        $pageText = $this->fetchPageText($website);

        $businessName = trim((string) ($input['business_name'] ?? ''));
        $industry = trim((string) ($input['industry'] ?? ''));

        $systemPrompt = <<<'PROMPT'
You analyze local business websites for onboarding. Respond ONLY with valid JSON using these keys:
- description: 2-3 sentences about what the business does and what it offers (German)
- target_audience: ideal customer profile with demographics, needs, pain points (German)
- unique_value_proposition: what makes the business unique vs competitors (German)
- additional_notes: optional extra context about goals or brand (German, can be short)
- city: city/location if clearly found on the website, else null

Write in German. Be concise and practical for social media marketing.
PROMPT;

        $userPrompt = collect([
            "Website URL: {$website}",
            $businessName !== '' ? "Business name: {$businessName}" : null,
            $industry !== '' ? "Industry: {$industry}" : null,
            "Website content excerpt:\n{$pageText}",
        ])->filter()->implode("\n\n");

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->acceptJson()
            ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                'model' => $this->model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'website' => ['Website analysis failed. Please try again or fill in the fields manually.'],
            ]);
        }

        $payload = $response->json();
        $content = data_get($payload, 'choices.0.message.content');
        $parsed = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($parsed)) {
            throw ValidationException::withMessages([
                'website' => ['Website analysis could not be parsed. Please try again.'],
            ]);
        }

        return new WebsiteAnalysisDTO(
            description: (string) ($parsed['description'] ?? ''),
            targetAudience: (string) ($parsed['target_audience'] ?? ''),
            uniqueValueProposition: (string) ($parsed['unique_value_proposition'] ?? ''),
            additionalNotes: (string) ($parsed['additional_notes'] ?? ''),
            city: filled($parsed['city'] ?? null) ? (string) $parsed['city'] : null,
            model: (string) ($payload['model'] ?? $this->model),
            tokensUsed: (int) data_get($payload, 'usage.total_tokens', 0),
        );
    }

    /**
     * Full business website analysis aligned with the klicklocal-webanalyze
     * skill (score, summary, services, SEO assessment, strengths/weaknesses,
     * brand tone, target audience, growth note). Separate from analyze() above,
     * which produces the lightweight onboarding profile fields.
     *
     * @param  array{
     *     website: string,
     *     business_name?: string|null,
     *     industry?: string|null
     * }  $input
     */
    public function analyzeBusiness(array $input): BusinessWebsiteAnalysisDTO
    {
        if ($this->apiKey === '' || $this->driver === 'fake') {
            return $this->fakeBusinessAnalysis($input);
        }

        $website = $this->normalizeUrl($input['website']);
        $pageText = $this->fetchPageText($website);

        $businessName = trim((string) ($input['business_name'] ?? ''));
        $industry = trim((string) ($input['industry'] ?? ''));

        $systemPrompt = <<<'PROMPT'
You are a local-business website auditor for the German market. Score and assess
the website like an experienced lead analyst. Respond ONLY with valid JSON using
exactly these keys:
- score: integer 0-100, the overall digital maturity score
- summary: 2-3 sentences summarizing the homepage / what the business does (German)
- services: array of 3-6 short strings naming the products/services offered (German)
- seo_assessment: 1-2 sentence headline assessment of the site's SEO — title, meta
  description, headings, local keywords, structured data (German)
- strengths: array of 3-5 concrete strengths (German)
- weaknesses: array of 3-5 concrete weaknesses / problems, demonstrable and specific (German)
- brand_tone: short phrase describing the brand's tone of voice (German)
- target_audience: ideal customer profile — demographics, needs (German)
- growth_note: 1-2 sentences on the biggest improvement levers and growth potential (German)

Derive the score from these weighted dimensions (max points): Technik & Infrastruktur (10),
SEO (20), Content & Vertrauen (10), Conversion (20), Social-Media-Aktivität (15),
Google-Sichtbarkeit (10), Marketing-Reife (10), Eigenständigkeit (5). When something
cannot be verified from the page text, assume an average rather than guessing extremes.

Score bands: 0-39 Kritisch, 40-59 Ausbaufähig, 60-79 Solide, 80-100 Stark.
Write all text in German. Be concrete and practical.
PROMPT;

        $userPrompt = collect([
            "Website URL: {$website}",
            $businessName !== '' ? "Business name: {$businessName}" : null,
            $industry !== '' ? "Industry: {$industry}" : null,
            "Website content excerpt:\n{$pageText}",
        ])->filter()->implode("\n\n");

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->acceptJson()
            ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                'model' => $this->model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'website' => ['Website analysis failed. Please try again later.'],
            ]);
        }

        $payload = $response->json();
        $content = data_get($payload, 'choices.0.message.content');
        $parsed = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($parsed)) {
            throw ValidationException::withMessages([
                'website' => ['Website analysis could not be parsed. Please try again.'],
            ]);
        }

        $score = (int) ($parsed['score'] ?? 0);
        $score = max(0, min(100, $score));

        return new BusinessWebsiteAnalysisDTO(
            score: $score,
            summary: (string) ($parsed['summary'] ?? ''),
            services: BusinessWebsiteAnalysisDTO::stringList($parsed['services'] ?? []),
            seoAssessment: (string) ($parsed['seo_assessment'] ?? ''),
            strengths: BusinessWebsiteAnalysisDTO::stringList($parsed['strengths'] ?? []),
            weaknesses: BusinessWebsiteAnalysisDTO::stringList($parsed['weaknesses'] ?? []),
            brandTone: (string) ($parsed['brand_tone'] ?? ''),
            targetAudience: (string) ($parsed['target_audience'] ?? ''),
            growthNote: (string) ($parsed['growth_note'] ?? ''),
            model: (string) ($payload['model'] ?? $this->model),
            tokensUsed: (int) data_get($payload, 'usage.total_tokens', 0),
        );
    }

    /**
     * @param  array{
     *     website: string,
     *     business_name?: string|null,
     *     industry?: string|null
     * }  $input
     */
    private function fakeBusinessAnalysis(array $input): BusinessWebsiteAnalysisDTO
    {
        $name = trim((string) ($input['business_name'] ?? '')) ?: 'Dein Unternehmen';
        $industry = trim((string) ($input['industry'] ?? '')) ?: 'lokales Unternehmen';
        $website = $this->normalizeUrl($input['website']);

        // Deterministic but plausible score in the "Ausbaufähig" band.
        $score = 48 + (strlen($name.$industry) % 18); // 48-65

        return new BusinessWebsiteAnalysisDTO(
            score: $score,
            summary: "{$name} ist ein {$industry} mit Online-Präsenz unter {$website}. "
                .'Die Website stellt das Angebot vor und richtet sich an Kundinnen und Kunden aus der Region.',
            services: [
                'Beratung & persönlicher Service',
                "Angebote im Bereich {$industry}",
                'Regionale Dienstleistungen',
            ],
            seoAssessment: 'Grundlegende SEO ist vorhanden, aber Title, Meta-Description und lokale '
                .'Keywords sind nicht optimal gepflegt. Strukturierte Daten (LocalBusiness) fehlen.',
            strengths: [
                'Klare Darstellung des Angebots',
                'Regionaler Bezug erkennbar',
                'Kontaktmöglichkeit vorhanden',
            ],
            weaknesses: [
                'Keine aktuellen Social-Media-Inhalte verlinkt',
                'Fehlende strukturierte Daten für lokale Suche',
                'Conversion-Elemente (Buchung/CTA) ausbaufähig',
            ],
            brandTone: 'Bodenständig, persönlich und vertrauenswürdig',
            targetAudience: "Lokale Kundinnen und Kunden, die {$industry}-Angebote in ihrer Nähe suchen — "
                .'qualitätsbewusst und regional verbunden.',
            growthNote: 'Mit aktiver Social-Media-Präsenz, gepflegtem Google-Profil und besseren '
                .'Conversion-Elementen lässt sich die lokale Sichtbarkeit deutlich steigern.',
            model: 'fake-gpt-5',
            tokensUsed: 0,
        );
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://'.$url;
        }

        return $url;
    }

    private function fetchPageText(string $url): string
    {
        try {
            $html = $this->urlFetcher->fetch($url, timeout: 15, headers: [
                'User-Agent' => 'KlicklocalBot/1.0 (+https://klicklocal.app)',
                'Accept' => 'text/html,application/xhtml+xml',
            ]);
        } catch (\Throwable) {
            return 'No website content could be fetched.';
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return Str::limit(trim($text), 8000, '…');
    }

    /**
     * @param  array{
     *     website: string,
     *     business_name?: string|null,
     *     industry?: string|null
     * }  $input
     */
    private function fakeAnalysis(array $input): WebsiteAnalysisDTO
    {
        $website = $this->normalizeUrl($input['website']);
        $name = trim((string) ($input['business_name'] ?? '')) ?: 'Dein Unternehmen';
        $industry = trim((string) ($input['industry'] ?? '')) ?: 'lokales Unternehmen';

        return new WebsiteAnalysisDTO(
            description: "{$name} ist ein {$industry} mit Online-Präsenz unter {$website}. "
                .'Wir bieten Produkte und Dienstleistungen für Kundinnen und Kunden in der Region — '
                .'passe diesen Text nach der Analyse gerne an.',
            targetAudience: "Lokale Kundinnen und Kunden, die {$industry}-Angebote in ihrer Nähe suchen — "
                .'qualitätsbewusst, regional verbunden und auf der Suche nach vertrauenswürdigen Anbietern.',
            uniqueValueProposition: "{$name} überzeugt durch persönlichen Service, regionale Verbundenheit "
                ."und ein klares Angebot im Bereich {$industry}.",
            additionalNotes: 'Platzhalter aus dem lokalen KI-Modus (OPENAI_DRIVER=fake). '
                .'Für echte Website-Analyse OPENAI_API_KEY in der Backend-.env setzen.',
            city: null,
            model: 'fake-gpt-5',
            tokensUsed: 0,
        );
    }
}
