<?php

namespace App\Services\Ai\DTOs;

class SentimentBatchDTO
{
    /**
     * @param  list<array{id: mixed, sentiment: mixed}>  $results  As returned by the
     *         model — NOT validated. Callers must validate ids and the sentiment
     *         enum strictly before persisting anything.
     */
    public function __construct(
        public readonly array $results,
        public readonly string $model,
        public readonly int $tokensUsed,
    ) {}
}
