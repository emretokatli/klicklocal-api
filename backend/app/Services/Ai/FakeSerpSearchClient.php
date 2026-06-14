<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\SerpSearchClientInterface;

/**
 * Deterministic SERP stub for local dev / tests (SERP_DRIVER=fake). Returns
 * three realistic-looking local competitors so the report generator has named
 * Wettbewerber to work with.
 */
class FakeSerpSearchClient implements SerpSearchClientInterface
{
    public function search(string $query): array
    {
        $competitors = [
            ['name' => 'Bella Vista', 'rating' => 4.6, 'reviews' => 312],
            ['name' => 'Stadtküche', 'rating' => 4.4, 'reviews' => 187],
            ['name' => 'Zum Goldenen Hirsch', 'rating' => 4.2, 'reviews' => 96],
        ];

        $results = array_map(fn ($c) => [
            'title' => "{$c['name']} – {$c['rating']}★ ({$c['reviews']} Bewertungen)",
            'url' => 'https://example.com/'.strtolower(preg_replace('/[^a-z]/i', '', $c['name'])),
            'snippet' => "{$c['name']} ist ein lokaler Anbieter mit {$c['reviews']} Google-Bewertungen.",
        ], $competitors);

        return [
            'results' => $results,
            'raw' => [
                'note' => 'fake SERP data (SERP_DRIVER=fake)',
                'local_results' => array_map(fn ($c) => [
                    'title' => $c['name'],
                    'rating' => $c['rating'],
                    'reviews' => $c['reviews'],
                ], $competitors),
            ],
        ];
    }
}
