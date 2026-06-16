<?php

namespace App\Services\SocialProviders\DTOs;

use Illuminate\Support\Carbon;

/**
 * One published post/video normalized across providers for content analysis.
 */
readonly class SocialMediaItemDTO
{
    public function __construct(
        public string $externalId,
        public string $provider,
        public string $postType,      // image | video | reel | carousel | text
        public ?string $caption,
        public ?string $permalink,
        public ?Carbon $publishedAt,
        public int $likes = 0,
        public int $comments = 0,
        public int $shares = 0,
        public int $reach = 0,
        public int $impressions = 0,
        public ?array $raw = null,
    ) {}

    public function engagement(): int
    {
        return $this->likes + $this->comments + $this->shares;
    }

    /** Hour of day (0-23) the post was published, or null. */
    public function hour(): ?int
    {
        return $this->publishedAt?->hour;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'provider' => $this->provider,
            'post_type' => $this->postType,
            'caption' => $this->caption,
            'permalink' => $this->permalink,
            'published_at' => $this->publishedAt?->toIso8601String(),
            'hour' => $this->hour(),
            'likes' => $this->likes,
            'comments' => $this->comments,
            'shares' => $this->shares,
            'reach' => $this->reach,
            'impressions' => $this->impressions,
            'engagement' => $this->engagement(),
        ];
    }
}
