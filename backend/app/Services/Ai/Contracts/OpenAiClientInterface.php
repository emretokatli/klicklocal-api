<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\DTOs\GeneratedContentDTO;

interface OpenAiClientInterface
{
    /**
     * Generate structured Instagram content from a system + user prompt and an optional image.
     *
     * @param  array<string, string>  $context  Flat business-profile context (for fake fallback / logging).
     */
    public function generateContent(
        string $systemPrompt,
        string $userPrompt,
        ?string $imageUrl,
        array $context = [],
    ): GeneratedContentDTO;
}
