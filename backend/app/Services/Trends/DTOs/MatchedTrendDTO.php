<?php

namespace App\Services\Trends\DTOs;

/**
 * A trend annotated for one business: whether it fits the business's industry,
 * a short AI comment, and a suggested content-format badge.
 */
readonly class MatchedTrendDTO
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public ?string $category,
        public int $score,
        public bool $fit,
        public string $comment,
        public string $suggestion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'score' => $this->score,
            'fit' => $this->fit,
            'comment' => $this->comment,
            'suggestion' => $this->suggestion,
        ];
    }
}
