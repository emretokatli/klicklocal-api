<?php

namespace App\Services\Ai\Contracts;

interface SerpSearchClientInterface
{
    /**
     * Returns top organic results for a query.
     *
     * @return array{results: array<int, array{title: string, url: string, snippet: string}>, raw: array<mixed>, error?: string}
     */
    public function search(string $query): array;
}
