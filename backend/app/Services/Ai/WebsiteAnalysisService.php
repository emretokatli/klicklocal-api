<?php

namespace App\Services\Ai;

use App\Services\Ai\DTOs\WebsiteAnalysisDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebsiteAnalysisService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $driver,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly int $timeout,
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
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'KlicklocalBot/1.0 (+https://klicklocal.app)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($url);
        } catch (\Throwable) {
            return 'No website content could be fetched.';
        }

        if ($response->failed()) {
            return 'No website content could be fetched.';
        }

        $html = (string) $response->body();
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
