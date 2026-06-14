<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Synthesises the customer-facing v2 lead report from the structured payload
 * collected by WebsiteDataCollector + SerpApiSearchClient + SocialProfileFetcher
 * using a SINGLE Anthropic Messages API call. No browsing, no tools, no
 * multi-turn agent loop — just data in, markdown out.
 */
class WebAnalyzeReportGenerator
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const ANTHROPIC_VERSION = '2023-06-01';

    private const TIMEOUT = 60;

    /**
     * Per-1k token rates (USD) for cost estimation. input/output.
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private const MODEL_RATES = [
        'haiku' => [0.00025, 0.00125],
        'sonnet' => [0.003, 0.015],
        'opus' => [0.015, 0.075],
    ];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{markdown: string, total_cost_usd: float, model: string, input_tokens: int, output_tokens: int}
     */
    public function generate(array $payload): array
    {
        if ($this->apiKey === '') {
            throw ValidationException::withMessages([
                'website' => ['ANTHROPIC_API_KEY is not configured.'],
            ]);
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::ANTHROPIC_VERSION,
        ])
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->post(self::ENDPOINT, [
                'model' => $this->model,
                'max_tokens' => 4096,
                'system' => $this->systemPrompt(),
                'messages' => [
                    ['role' => 'user', 'content' => $this->buildUserMessage($payload)],
                ],
            ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'website' => ['Report generation failed (Anthropic HTTP '.$response->status().').'],
            ]);
        }

        $markdown = trim((string) data_get($response->json(), 'content.0.text', ''));

        if ($markdown === '' || ! str_contains($markdown, '# Lead-Analyse')) {
            throw ValidationException::withMessages([
                'website' => ['Report generation returned an invalid report.'],
            ]);
        }

        $inputTokens = (int) data_get($response->json(), 'usage.input_tokens', 0);
        $outputTokens = (int) data_get($response->json(), 'usage.output_tokens', 0);

        return [
            'markdown' => $markdown,
            'total_cost_usd' => $this->estimateCost($inputTokens, $outputTokens),
            'model' => $this->model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildUserMessage(array $payload): string
    {
        // Defensive: drop any raw HTML that may have crept into the payload.
        unset($payload['raw_html'], $payload['html']);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return "Erstelle aus diesen strukturierten Analysedaten den vollständigen Lead-Report. "
            ."Verwende ausschließlich die hier enthaltenen Fakten:\n\n```json\n{$json}\n```";
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Du bist der Lead-Analyst von Klicklocal. Du erhältst strukturierte JSON-Daten über die Website,
die Social-Media-Profile, die Google-Sichtbarkeit (SerpAPI) und die Wettbewerber eines lokalen
Unternehmens. Erzeuge daraus einen vollständigen, deutschsprachigen Lead-Analyse-Report.

WICHTIGE REGELN
- Antworte NUR mit dem Markdown-Report, ohne Vorrede.
- Der Report ist kundenseitig. Nenne NIRGENDS Modellname, Kosten, Laufzeit oder interne
  Score-Debatten in den kundenseitigen Abschnitten.
- Nutze AUSSCHLIESSLICH Fakten aus den JSON-Daten. Erfinde keine Zahlen.
- Felder mit null/leer sind "nicht verifizierbar" → halbe Punkte in der Kategorie, klar so benennen.
- Jede Wettbewerber-Aussage MUSS einen Namen aus den SerpAPI-Daten verwenden (results/local_results) —
  niemals "Ihre Wettbewerber" ohne Namen.
- Wenn site_reachable=false: das ist der wichtigste Aufhänger ("Ihre Website ist aktuell nicht
  erreichbar") und kostet fast alle Eigenständigkeits-/Technik-Punkte.

SCORING (v2-Rubrik, Summe = 100)
Technik & Infrastruktur 10 · SEO 20 · Content & Vertrauen 10 · Conversion 20 ·
Social-Media-Aktivität 15 · Google-Sichtbarkeit 10 · Marketing-Reife 10 · Eigenständigkeit 5.
Score-Bänder: 0–39 Kritisch · 40–59 Ausbaufähig · 60–79 Solide · 80–100 Stark.

"So hilft Klicklocal" — mappe Findings NUR auf reale Klicklocal-Leistungen:
Instagram/TikTok/Facebook Post-Planung, KI-Content-Generierung, Reel-Studio (15-Sekunden-Reels),
Post-Kalender. Website-Umbauten als Partner-/Zusatzleistung kennzeichnen.

WACHSTUMSPROGNOSE
- Die "Annahmen:"-Zeile ist PFLICHT.
- Nutze den Branchen-Bon als Annahme und kennzeichne ihn als "Branchenwert (Annahme)":
  Gastro ~18–22 €, Friseur ~40–70 €, Handwerk ~500–3.000 €.
- 3-Monats-Horizont: nur Conversion-/GBP-/Social-Effekte. 6-Monats-Horizont: plus frühe SEO-Effekte.
- Gib immer eine konservativ–optimistische Spanne an.

VERWENDE EXAKT DIESE VORLAGE (Reihenfolge und Überschriften unverändert):

# Lead-Analyse: [Business name] — [URL]

## Gesamtbewertung: XX/100 — [Band-Label]
| Kategorie | Punkte |
|---|---|
| Technik & Infrastruktur | x/10 |
| SEO | x/20 |
| Content & Vertrauen | x/10 |
| Conversion | x/20 |
| Social-Media-Aktivität | x/15 |
| Google-Sichtbarkeit | x/10 |
| Marketing-Reife | x/10 |
| Eigenständigkeit | x/5 |

## Stärken
- ...

## Schwächen & Probleme
- ...

## Social-Media-Audit
Pro Kanal: Status, letzter Post, Frequenz, Follower, Formate — und der Vergleich zum aktivsten
lokalen Wettbewerber.

## Google-Sichtbarkeit & Wettbewerb
GBP-Status, Rating/Bewertungen, Maps-Pack-Position, Top-2–3-Wettbewerber namentlich mit Rating &
Bewertungszahl.

## Verbesserungspotenziale (priorisiert)
1. [Quick Win] ...
2. ...

## So hilft Klicklocal
- [Finding] → [konkrete Klicklocal-Leistung]

## SEO-Bewertung
Title/Description, Überschriften, Schema.org, lokale Keywords — plus das SEO-Teilergebnis (x/20)
in einem Satz eingeordnet.

## Kontaktdaten (CRM)
Firma/Inhaber, Adresse, Telefon, E-Mail, USt-IdNr, Social, Öffnungszeiten

## Lokales Marktpotenzial
Marktdichte, aktuelle Sichtbarkeit, Lücke zur lokalen Spitze — mit namentlich genannten
Wettbewerbern.

## Wachstumsprognose
| Horizont | Wachstum | Umsatzpotenzial |
|---|---|---|
| 3 Monate | +x–y % | ~A–B € |
| 6 Monate | +x–y % | ~C–D € |

Annahmen: ... (Ø Bon als Branchenwert (Annahme), Baseline, Hebel) — PFLICHTZEILE
*Schätzung — keine Garantie; präzisierbar mit echten Kundendaten.*

## Gesprächsaufhänger (3–5 konkrete Punkte)
1. ...

--- Interne Notizen (nicht für den Kunden) ---
Liste hier: welche Felder null/nicht verifizierbar waren und warum, sowie die Scoring-Abwägungen.
Dieser Block darf NICHT im Kunden-PDF erscheinen.
PROMPT;
    }

    private function estimateCost(int $inputTokens, int $outputTokens): float
    {
        $key = 'sonnet';
        $model = strtolower($this->model);

        foreach (array_keys(self::MODEL_RATES) as $family) {
            if (str_contains($model, $family)) {
                $key = $family;
                break;
            }
        }

        [$inRate, $outRate] = self::MODEL_RATES[$key];

        return round(($inputTokens / 1000) * $inRate + ($outputTokens / 1000) * $outRate, 6);
    }
}
