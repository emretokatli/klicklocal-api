<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\DTOs\ContentPlanSuggestionDTO;
use App\Services\Ai\DTOs\GeneratedContentDTO;
use App\Services\Ai\DTOs\GeneratedImageDTO;
use App\Services\Ai\DTOs\SentimentBatchDTO;
use App\Services\Ai\DTOs\SuggestedReplyDTO;

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

    /**
     * Classify the sentiment of a batch of social media comments in one
     * JSON-mode chat call (fake driver: keyword heuristics on the text).
     * Results are returned raw — callers must validate them strictly.
     *
     * @param  string  $systemPrompt  Defines the classes; comments are mostly German.
     * @param  list<array{id: int, text: string}>  $comments
     */
    public function classifySentiments(string $systemPrompt, array $comments): SentimentBatchDTO;

    /**
     * Suggest a German reply to one social media comment in a JSON-mode chat
     * call (fake driver: sentiment-keyed canned reply).
     *
     * @param  array<string, string>  $context  Business-profile context (for fake fallback / logging).
     */
    public function suggestCommentReply(
        string $systemPrompt,
        string $userPrompt,
        array $context = [],
    ): SuggestedReplyDTO;

    /**
     * Generate a short German marketing comment for each trend in a single
     * JSON-mode chat call. Returns comments keyed by trend id. The fake driver
     * returns deterministic German placeholders.
     *
     * @param  array<int, array{title: string, description?: string|null, fit?: bool}>  $trends  keyed by trend id
     * @param  array<string, string>  $context  Business-profile context (for fake fallback / logging).
     * @return array<int, string>  comment keyed by trend id
     */
    public function commentOnTrends(string $systemPrompt, array $trends, array $context = []): array;

    /**
     * Summarize normalized social analytics into a German content-plan
     * suggestion (best posting times, recommended post types, content ideas) in
     * one JSON-mode chat call. The fake driver returns German placeholders.
     *
     * @param  array<string, mixed>  $analytics  Aggregated/normalized analytics payload.
     * @param  array<string, string>  $context    Business-profile context (for fake fallback / logging).
     */
    public function suggestContentPlan(
        string $systemPrompt,
        array $analytics,
        array $context = [],
    ): ContentPlanSuggestionDTO;
}
