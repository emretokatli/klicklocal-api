<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\DTOs\GeneratedContentDTO;
use App\Services\Ai\DTOs\GeneratedImageDTO;

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

    /**
     * Generate an image using gpt-image-1 (or fake placeholder).
     * Returns a GeneratedImageDTO with a public URL.
     *
     * @param  array<string, string>  $context  Business profile context for fake fallback.
     */
    public function generateImage(
        string $prompt,
        array $context = [],
        string $size = '1024x1024',
        string $quality = 'standard',
    ): GeneratedImageDTO;
}
