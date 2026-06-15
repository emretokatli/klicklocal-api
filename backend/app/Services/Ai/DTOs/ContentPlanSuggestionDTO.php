<?php

namespace App\Services\Ai\DTOs;

readonly class ContentPlanSuggestionDTO
{
    /**
     * @param  list<string>  $bestTimes
     * @param  list<string>  $recommendedPostTypes
     * @param  list<array{title: string, format: string, reason: string}>  $contentIdeas
     */
    public function __construct(
        public string $summary,
        public array $bestTimes,
        public array $recommendedPostTypes,
        public array $contentIdeas,
        public string $model,
        public int $tokensUsed = 0,
        public ?array $raw = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'best_times' => $this->bestTimes,
            'recommended_post_types' => $this->recommendedPostTypes,
            'content_ideas' => $this->contentIdeas,
            'model' => $this->model,
            'tokens_used' => $this->tokensUsed,
        ];
    }
}
