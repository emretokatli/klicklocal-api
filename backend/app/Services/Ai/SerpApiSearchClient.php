<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\SerpSearchClientInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google SERP lookups via serpapi.com — used for competitor and Google
 * Business Profile context in the code-first WebAnalyze pipeline. Never throws:
 * on failure it returns an empty result set with an `error` key so the report
 * generator can degrade gracefully.
 */
class SerpApiSearchClient implements SerpSearchClientInterface
{
    private const ENDPOINT = 'https://serpapi.com/search';

    private const TIMEOUT = 10;

    public function __construct(
        private readonly string $apiKey,
    ) {}

    public function search(string $query): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->acceptJson()
                ->get(self::ENDPOINT, [
                    'q' => $query,
                    'location' => 'Germany',
                    'hl' => 'de',
                    'gl' => 'de',
                    'engine' => 'google',
                    'api_key' => $this->apiKey,
                ]);

            if ($response->failed()) {
                return ['results' => [], 'raw' => [], 'error' => "SerpAPI HTTP {$response->status()}"];
            }

            $payload = $response->json();

            $results = collect($payload['organic_results'] ?? [])
                ->take(5)
                ->map(fn ($item) => [
                    'title' => (string) ($item['title'] ?? ''),
                    'url' => (string) ($item['link'] ?? ''),
                    'snippet' => (string) ($item['snippet'] ?? ''),
                ])
                ->values()
                ->all();

            return [
                'results' => $results,
                'raw' => [
                    'local_results' => $payload['local_results'] ?? [],
                ],
            ];
        } catch (Throwable $e) {
            return ['results' => [], 'raw' => [], 'error' => $e->getMessage()];
        }
    }
}
