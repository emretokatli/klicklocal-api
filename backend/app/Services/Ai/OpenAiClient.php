<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\OpenAiClientInterface;
use App\Services\Ai\DTOs\GeneratedContentDTO;
use App\Services\Ai\DTOs\GeneratedImageDTO;
use App\Services\Ai\DTOs\SentimentBatchDTO;
use App\Services\Ai\DTOs\SuggestedReplyDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OpenAiClient implements OpenAiClientInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly string $sentimentModel = 'gpt-4o-mini',
    ) {}

    public function generateContent(
        string $systemPrompt,
        string $userPrompt,
        ?string $imageUrl,
        array $context = [],
    ): GeneratedContentDTO {
        if ($this->apiKey === '') {
            throw ValidationException::withMessages([
                'ai' => ['AI is not configured. Set OPENAI_API_KEY on the server.'],
            ]);
        }

        $userContent = [
            ['type' => 'text', 'text' => $userPrompt],
        ];

        if ($imageUrl !== null && $imageUrl !== '') {
            $userContent[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $imageUrl],
            ];
        }

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->acceptJson()
            ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                'model' => $this->model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);

        if ($response->failed()) {
            \Illuminate\Support\Facades\Log::error('OpenAI generateContent failed', [
                'status' => $response->status(),
                'body'   => $response->json() ?? $response->body(),
            ]);
            throw ValidationException::withMessages([
                'ai' => ['The AI provider returned an error. Please try again.'],
            ]);
        }

        $payload = $response->json();
        $content = data_get($payload, 'choices.0.message.content');

        $parsed = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($parsed)) {
            throw ValidationException::withMessages([
                'ai' => ['The AI response could not be parsed. Please try again.'],
            ]);
        }

        return new GeneratedContentDTO(
            caption: (string) ($parsed['caption'] ?? ''),
            storyText: (string) ($parsed['story_text'] ?? ''),
            hashtags: $this->normalizeHashtags($parsed['hashtags'] ?? []),
            callToAction: (string) ($parsed['call_to_action'] ?? ''),
            model: (string) ($payload['model'] ?? $this->model),
            tokensUsed: (int) data_get($payload, 'usage.total_tokens', 0),
            raw: $parsed,
        );
    }

    public function generateImage(
        string $prompt,
        array $context = [],
        string $size = '1024x1024',
        string $quality = 'standard',
    ): GeneratedImageDTO {
        if ($this->apiKey === '') {
            throw ValidationException::withMessages([
                'ai' => ['AI is not configured. Set OPENAI_API_KEY on the server.'],
            ]);
        }

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->acceptJson()
            ->post(rtrim($this->baseUrl, '/').'/images/generations', [
                'model'   => 'gpt-image-1',
                'prompt'  => $prompt,
                'n'       => 1,
                'size'    => $size,
                'quality' => $quality,
            ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'ai' => ['Image generation failed. Please try again.'],
            ]);
        }

        $payload = $response->json();
        $imageUrl = data_get($payload, 'data.0.url', '');
        $revisedPrompt = data_get($payload, 'data.0.revised_prompt', '');

        if (empty($imageUrl)) {
            throw ValidationException::withMessages([
                'ai' => ['No image returned from AI provider.'],
            ]);
        }

        return new GeneratedImageDTO(
            imageUrl: $imageUrl,
            model: 'gpt-image-1',
            revisedPrompt: $revisedPrompt,
        );
    }

    public function classifySentiments(string $systemPrompt, array $comments): SentimentBatchDTO
    {
        if ($this->apiKey === '') {
            throw ValidationException::withMessages([
                'ai' => ['AI is not configured. Set OPENAI_API_KEY on the server.'],
            ]);
        }

        $userPrompt = json_encode(
            ['comments' => $comments],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->acceptJson()
            ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                'model' => $this->sentimentModel,
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if ($response->failed()) {
            \Illuminate\Support\Facades\Log::error('OpenAI classifySentiments failed', [
                'status' => $response->status(),
                'body'   => $response->json() ?? $response->body(),
            ]);
            throw ValidationException::withMessages([
                'ai' => ['The AI provider returned an error. Please try again.'],
            ]);
        }

        $payload = $response->json();
        $content = data_get($payload, 'choices.0.message.content');
        $parsed = is_string($content) ? json_decode($content, true) : null;
        $results = is_array($parsed) ? ($parsed['results'] ?? null) : null;

        return new SentimentBatchDTO(
            results: is_array($results) ? array_values($results) : [],
            model: (string) ($payload['model'] ?? $this->sentimentModel),
            tokensUsed: (int) data_get($payload, 'usage.total_tokens', 0),
        );
    }

    public function suggestCommentReply(
        string $systemPrompt,
        string $userPrompt,
        array $context = [],
    ): SuggestedReplyDTO {
        if ($this->apiKey === '') {
            throw ValidationException::withMessages([
                'ai' => ['AI is not configured. Set OPENAI_API_KEY on the server.'],
            ]);
        }

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
            \Illuminate\Support\Facades\Log::error('OpenAI suggestCommentReply failed', [
                'status' => $response->status(),
                'body'   => $response->json() ?? $response->body(),
            ]);
            throw ValidationException::withMessages([
                'ai' => ['The AI provider returned an error. Please try again.'],
            ]);
        }

        $payload = $response->json();
        $content = data_get($payload, 'choices.0.message.content');
        $parsed = is_string($content) ? json_decode($content, true) : null;
        $reply = is_array($parsed) ? trim((string) ($parsed['reply'] ?? '')) : '';

        if ($reply === '') {
            throw ValidationException::withMessages([
                'ai' => ['The AI response could not be parsed. Please try again.'],
            ]);
        }

        return new SuggestedReplyDTO(
            replyText: $reply,
            model: (string) ($payload['model'] ?? $this->model),
            tokensUsed: (int) data_get($payload, 'usage.total_tokens', 0),
        );
    }

    public function commentOnTrends(string $systemPrompt, array $trends, array $context = []): array
    {
        if ($this->apiKey === '') {
            throw ValidationException::withMessages([
                'ai' => ['AI is not configured. Set OPENAI_API_KEY on the server.'],
            ]);
        }

        // Reshape to a stable list with explicit ids for the model.
        $list = [];
        foreach ($trends as $id => $trend) {
            $list[] = [
                'id' => (int) $id,
                'title' => (string) ($trend['title'] ?? ''),
                'description' => (string) ($trend['description'] ?? ''),
                'fit' => ! empty($trend['fit']),
            ];
        }

        $userPrompt = json_encode(
            ['business' => $context, 'trends' => $list],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

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
            \Illuminate\Support\Facades\Log::error('OpenAI commentOnTrends failed', [
                'status' => $response->status(),
                'body'   => $response->json() ?? $response->body(),
            ]);
            throw ValidationException::withMessages([
                'ai' => ['The AI provider returned an error. Please try again.'],
            ]);
        }

        $payload = $response->json();
        $content = data_get($payload, 'choices.0.message.content');
        $parsed = is_string($content) ? json_decode($content, true) : null;
        $rows = is_array($parsed) ? ($parsed['comments'] ?? null) : null;

        $comments = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (isset($row['id'])) {
                    $comments[(int) $row['id']] = trim((string) ($row['comment'] ?? ''));
                }
            }
        }

        return $comments;
    }

    /**
     * @param  mixed  $hashtags
     * @return list<string>
     */
    private function normalizeHashtags(mixed $hashtags): array
    {
        if (is_string($hashtags)) {
            $hashtags = preg_split('/[\s,]+/', $hashtags) ?: [];
        }

        if (! is_array($hashtags)) {
            return [];
        }

        return collect($hashtags)
            ->map(fn ($tag) => '#'.ltrim(trim((string) $tag), '#'))
            ->filter(fn ($tag) => $tag !== '#')
            ->unique()
            ->values()
            ->all();
    }
}
