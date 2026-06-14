<?php

namespace App\Services\Planning\DTOs;

/**
 * One suggested content slot in the weekly plan: which day, which category,
 * which platform, plus a short German idea and an optional linked trend.
 */
readonly class PlanSuggestionDTO
{
    public function __construct(
        public string $day,
        public string $date,
        public string $category,
        public string $categoryLabel,
        public string $platform,
        public string $idea,
        public ?string $trendTitle = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'day' => $this->day,
            'date' => $this->date,
            'category' => $this->category,
            'category_label' => $this->categoryLabel,
            'platform' => $this->platform,
            'idea' => $this->idea,
            'trend_title' => $this->trendTitle,
        ];
    }
}
