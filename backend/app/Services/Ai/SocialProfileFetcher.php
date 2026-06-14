<?php

namespace App\Services\Ai;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Scrapes publicly-visible metrics from social profile pages (follower count,
 * post count, recency) without logging in. Both methods always return a fully
 * populated array and never throw — a login wall or block degrades to null
 * metrics with an `error` note so the report can still mention the channel.
 */
class SocialProfileFetcher
{
    private const TIMEOUT = 8;

    private const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    /**
     * @return array{handle: string, exists: bool, followers: int|null, post_count: int|null, last_post: string|null, posts_per_week: float|null, error: string|null}
     */
    public function fetchInstagram(string $handle): array
    {
        $handle = $this->normalizeHandle($handle);
        $base = [
            'handle' => $handle,
            'exists' => true,
            'followers' => null,
            'post_count' => null,
            'last_post' => null,
            'posts_per_week' => null,
            'error' => null,
        ];

        $html = $this->fetch("https://www.instagram.com/{$handle}/");

        if ($html === null) {
            return ['error' => 'could not scrape'] + $base;
        }

        $followers = $this->intMatch('/"edge_followed_by":\{"count":(\d+)\}/', $html);
        $postCount = $this->intMatch('/"edge_owner_to_timeline_media":\{"count":(\d+)\}/', $html);
        $lastTimestamp = $this->intMatch('/"taken_at_timestamp":(\d+)/', $html);

        if ($followers === null && $postCount === null && $lastTimestamp === null) {
            return ['error' => 'could not scrape'] + $base;
        }

        $lastPost = $lastTimestamp !== null
            ? CarbonImmutable::createFromTimestamp($lastTimestamp)->toDateString()
            : null;

        return [
            'handle' => $handle,
            'exists' => true,
            'followers' => $followers,
            'post_count' => $postCount,
            'last_post' => $lastPost,
            'posts_per_week' => $this->postsPerWeek($postCount, $lastTimestamp),
            'error' => null,
        ];
    }

    /**
     * @return array{handle: string, exists: bool, followers: int|null, post_count: int|null, last_post: string|null, posts_per_week: float|null, error: string|null}
     */
    public function fetchFacebook(string $url): array
    {
        $handle = $this->normalizeHandle($url);
        $base = [
            'handle' => $handle,
            'exists' => true,
            'followers' => null,
            'post_count' => null,
            'last_post' => null,
            'posts_per_week' => null,
            'error' => null,
        ];

        $html = $this->fetch($this->absoluteFacebookUrl($url));

        if ($html === null) {
            return ['error' => 'could not scrape'] + $base;
        }

        // Public FB pages expose follower/like counts in og:description text,
        // e.g. "1.234 likes · 56 talking about this".
        $description = $this->stringMatch('/<meta[^>]*property=["\']og:description["\'][^>]*content=["\']([^"\']*)["\']/i', $html);
        $followers = null;

        if ($description !== null && preg_match('/([\d.,]+)\s*(?:likes|followers|Gefällt mir|Abonnenten)/i', $description, $m) === 1) {
            $followers = (int) str_replace(['.', ','], '', $m[1]);
        }

        $lastTimestamp = $this->intMatch('/"publish_time":(\d{10})/', $html);
        $lastPost = $lastTimestamp !== null
            ? CarbonImmutable::createFromTimestamp($lastTimestamp)->toDateString()
            : null;

        if ($followers === null && $lastPost === null) {
            return ['error' => 'could not scrape'] + $base;
        }

        return [
            'handle' => $handle,
            'exists' => true,
            'followers' => $followers,
            'post_count' => null,
            'last_post' => $lastPost,
            'posts_per_week' => null,
            'error' => null,
        ];
    }

    private function postsPerWeek(?int $postCount, ?int $firstTimestamp): ?float
    {
        if ($postCount === null || $postCount <= 0 || $firstTimestamp === null) {
            return null;
        }

        $ageWeeks = max(1.0, (time() - $firstTimestamp) / (7 * 86400));

        return round($postCount / $ageWeeks, 2);
    }

    private function fetch(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => self::BROWSER_UA,
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'de-DE,de;q=0.9,en;q=0.8',
            ])->timeout(self::TIMEOUT)->get($url);

            if ($response->failed()) {
                return null;
            }

            return (string) $response->body();
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeHandle(string $value): string
    {
        $value = trim($value);

        if (preg_match('~https?://[^/]+/([^/?#]+)~i', $value, $m) === 1) {
            $value = $m[1];
        }

        return trim($value, "@/ \t\n\r");
    }

    private function absoluteFacebookUrl(string $url): string
    {
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return 'https://www.facebook.com/'.ltrim($url, '/');
    }

    private function intMatch(string $pattern, string $subject): ?int
    {
        if (preg_match($pattern, $subject, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    private function stringMatch(string $pattern, string $subject): ?string
    {
        if (preg_match($pattern, $subject, $m) === 1) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }
}
