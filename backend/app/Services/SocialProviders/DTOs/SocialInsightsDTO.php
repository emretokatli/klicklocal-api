<?php

namespace App\Services\SocialProviders\DTOs;

/**
 * Account-level insights, normalized across providers.
 */
readonly class SocialInsightsDTO
{
    public function __construct(
        public int $followers = 0,
        public int $reach = 0,
        public int $impressions = 0,
        public int $profileViews = 0,
        public string $period = 'day',
        public ?array $raw = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'followers' => $this->followers,
            'reach' => $this->reach,
            'impressions' => $this->impressions,
            'profile_views' => $this->profileViews,
            'period' => $this->period,
        ];
    }
}
