<?php

namespace App\Services\Ai\DTOs;

class GeneratedContentDTO
{
    /**
     * @param  list<string>  $hashtags
     * @param  array<string, mixed>|null  $raw
     */
    public function __construct(
        public readonly string $caption,
        public readonly string $storyText,
        public readonly array $hashtags,
        public readonly string $callToAction,
        public readonly string $model,
        public readonly int $tokensUsed = 0,
        public readonly ?array $raw = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'caption' => $this->caption,
            'story_text' => $this->storyText,
            'hashtags' => $this->hashtags,
            'call_to_action' => $this->callToAction,
            'model' => $this->model,
            'tokens_used' => $this->tokensUsed,
        ];
    }
}
