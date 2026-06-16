<?php

namespace App\Services\Ai\DTOs;

readonly class WebsiteAnalysisDTO
{
    /**
     * @param  list<array{caption: string, hashtags: list<string>, suggested_image_idea: string}>  $samplePosts
     */
    public function __construct(
        public string $description,
        public string $targetAudience,
        public string $uniqueValueProposition,
        public string $additionalNotes,
        public ?string $city,
        public string $model,
        public int $tokensUsed,
        public array $samplePosts = [],
    ) {}

    /**
     * Normalize a raw `sample_posts` value (from OpenAI JSON) into exactly the
     * shape the onboarding UI expects. Drops malformed entries and caps at 3.
     *
     * @return list<array{caption: string, hashtags: list<string>, suggested_image_idea: string}>
     */
    public static function normalizeSamplePosts(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $posts = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            $caption = trim((string) ($item['caption'] ?? ''));
            if ($caption === '') {
                continue;
            }

            $hashtags = [];
            if (is_array($item['hashtags'] ?? null)) {
                foreach ($item['hashtags'] as $tag) {
                    $tag = trim((string) $tag);
                    if ($tag === '') {
                        continue;
                    }
                    $hashtags[] = str_starts_with($tag, '#') ? $tag : '#'.ltrim($tag, '#');
                }
            }

            $posts[] = [
                'caption' => $caption,
                'hashtags' => array_values($hashtags),
                'suggested_image_idea' => trim((string) ($item['suggested_image_idea'] ?? '')),
            ];

            if (count($posts) === 3) {
                break;
            }
        }

        return $posts;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'target_audience' => $this->targetAudience,
            'unique_value_proposition' => $this->uniqueValueProposition,
            'additional_notes' => $this->additionalNotes,
            'city' => $this->city,
            'sample_posts' => $this->samplePosts,
        ];
    }
}
