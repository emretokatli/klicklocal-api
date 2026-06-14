<?php

namespace Tests\Feature;

use App\Services\Ai\CodeFirstWebAnalyzeClient;
use App\Services\Ai\FakeSerpSearchClient;
use App\Services\Ai\SocialProfileFetcher;
use App\Services\Ai\WebAnalyzeReportGenerator;
use App\Services\Ai\WebsiteDataCollector;
use App\Support\SafeUrlFetcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CodeFirstWebAnalyzeClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('webanalyze.cache_driver', 'array');
        config()->set('webanalyze.cache_ttl_hours', 168);
    }

    private function client(): CodeFirstWebAnalyzeClient
    {
        return new CodeFirstWebAnalyzeClient(
            dataCollector: new WebsiteDataCollector(
                new SafeUrlFetcher(fn (string $host): array => ['93.184.216.34']),
            ),
            searchClient: new FakeSerpSearchClient,
            socialFetcher: new SocialProfileFetcher,
            generator: new WebAnalyzeReportGenerator('test-key', 'claude-haiku-4-5-20251001'),
        );
    }

    private function reportMarkdown(): string
    {
        return <<<'MD'
# Lead-Analyse: Restaurant Beispiel — https://example-gastro.de

## Gesamtbewertung: 58/100 — Ausbaufähig
| Kategorie | Punkte |
|---|---|
| Technik & Infrastruktur | 7/10 |

## Stärken
- HTTPS aktiv

--- Interne Notizen (nicht für den Kunden) ---
Test.
MD;
    }

    private function fakeHttp(?int &$anthropicCalls = null): void
    {
        $anthropicCalls = 0;

        $home = '<html lang="de"><head><title>Restaurant Beispiel München</title></head>'
            .'<body><a href="https://instagram.com/example_gastro">IG</a>'
            .'<a href="/impressum">Impressum</a></body></html>';

        $impressum = '<html><body>Beispielstraße 12, 80331 München. info@example-gastro.de</body></html>';

        $ig = 'window._sharedData={"edge_followed_by":{"count":900},'
            .'"edge_owner_to_timeline_media":{"count":50},"taken_at_timestamp":1700000000};';

        Http::fake([
            'https://api.anthropic.com/*' => function () use (&$anthropicCalls) {
                $anthropicCalls++;

                return Http::response([
                    'content' => [['type' => 'text', 'text' => $this->reportMarkdown()]],
                    'usage' => ['input_tokens' => 1200, 'output_tokens' => 800],
                ], 200);
            },
            'https://www.instagram.com/*' => Http::response($ig, 200, ['Content-Type' => 'text/html']),
            'https://example-gastro.de/impressum' => Http::response($impressum, 200, ['Content-Type' => 'text/html']),
            'https://example-gastro.de' => Http::response($home, 200, ['Content-Type' => 'text/html']),
        ]);
    }

    public function test_produces_report_and_caches_result(): void
    {
        $this->fakeHttp($anthropicCalls);

        $client = $this->client();
        $website = 'https://example-gastro.de';

        $first = $client->analyze($website);

        $this->assertStringContainsString('# Lead-Analyse', $first->reportMarkdown);
        $this->assertSame(58, $first->score);
        $this->assertSame('Ausbaufähig', $first->band);
        $this->assertSame(1, $first->numTurns);
        $this->assertGreaterThan(0, $first->totalCostUsd);
        $this->assertLessThanOrEqual(0.05, $first->totalCostUsd);
        $this->assertFalse($first->cached);
        $this->assertSame(1, $anthropicCalls);

        // Second call with the same URL is served from cache — no new Anthropic call.
        $second = $client->analyze($website);

        $this->assertTrue($second->cached);
        $this->assertSame(58, $second->score);
        $this->assertSame(1, $anthropicCalls, 'Anthropic must be called exactly once across both runs.');

        // Cache can be cleared, forcing a fresh run.
        Cache::driver('array')->forget($client->cacheKey($website));
        $client->analyze($website);
        $this->assertSame(2, $anthropicCalls);
    }
}
