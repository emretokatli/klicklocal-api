<?php

namespace App\Services\SocialProviders\Fake\Concerns;

use App\Services\SocialProviders\DTOs\SocialInsightsDTO;
use App\Services\SocialProviders\DTOs\SocialMediaItemDTO;
use Illuminate\Support\Carbon;

/**
 * Deterministic sample analytics for the fake social drivers so the content
 * analysis + AI content-plan flow can run end to end without real API access.
 */
trait SimulatesContentAnalysis
{
    /**
     * @return list<SocialMediaItemDTO>
     */
    public function fetchRecentMedia(int $limit = 25): array
    {
        $platform = $this->platform();
        $seed = crc32($platform.$this->account->id);
        $types = $platform === 'tiktok'
            ? ['video', 'video', 'video']
            : ['image', 'reel', 'carousel', 'video'];

        $count = min($limit, 8);
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $type = $types[($seed + $i) % count($types)];
            $likes = 40 + (($seed + $i * 13) % 260);
            $comments = 3 + (($seed + $i * 7) % 40);
            $shares = $i % 4;
            $reach = ($likes + $comments) * (8 + ($i % 5));
            $hour = [8, 12, 18, 20][($seed + $i) % 4];

            $items[] = new SocialMediaItemDTO(
                externalId: "{$platform}_demo_{$this->account->id}_{$i}",
                provider: $platform,
                postType: $type,
                caption: "Beispiel-Beitrag #{$i} (Demo-Daten)",
                permalink: "https://example.com/{$platform}/demo/{$i}",
                publishedAt: Carbon::now()->subDays($i + 1)->setTime($hour, 0),
                likes: $likes,
                comments: $comments,
                shares: $shares,
                reach: $reach,
                impressions: (int) ($reach * 1.4),
                raw: ['simulated' => true],
            );
        }

        return $items;
    }

    public function fetchInsights(): SocialInsightsDTO
    {
        $seed = crc32($this->platform().$this->account->id);

        return new SocialInsightsDTO(
            followers: 800 + ($seed % 4000),
            reach: 2000 + ($seed % 8000),
            impressions: 5000 + ($seed % 15000),
            profileViews: 200 + ($seed % 1500),
            period: 'day',
            raw: ['simulated' => true],
        );
    }
}
