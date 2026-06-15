<?php

namespace App\Services\SocialProviders\Facebook;

use App\Enums\SocialAccountStatus;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\SocialProviders\Base\BaseSocialProvider;
use App\Services\SocialProviders\Contracts\AnalyzesContent;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;
use App\Services\SocialProviders\DTOs\SocialInsightsDTO;
use App\Services\SocialProviders\DTOs\SocialMediaItemDTO;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Facebook Pages API provider. Publishes to a Page feed (and the photo/video
 * edges) using the Page access token stored on the social account.
 */
class FacebookProvider extends BaseSocialProvider implements AnalyzesContent
{
    public function __construct(
        SocialAccount $account,
        private readonly FacebookOAuthService $oauth,
        private readonly FacebookPublishingService $publishing,
    ) {
        parent::__construct($account);
    }

    public function platform(): string
    {
        return 'facebook';
    }

    public function publish(Post $post): PublishResponseDTO
    {
        $this->ensureCapability('publish');
        $this->ensureValidAccount();

        $post->loadMissing('media');

        return $this->publishing->publishPost($this->account, $post);
    }

    public function validateAccount(): bool
    {
        if ($this->account->status !== SocialAccountStatus::Connected) {
            return false;
        }

        if (! filled($this->account->access_token)) {
            return false;
        }

        if ($this->account->isTokenExpired()) {
            return false;
        }

        return $this->oauth->validateAccessToken($this->account->access_token);
    }

    /**
     * @return list<SocialMediaItemDTO>
     */
    public function fetchRecentMedia(int $limit = 25): array
    {
        $pageId = $this->pageId();
        $base = rtrim((string) config('facebook.graph_base_url'), '/');
        $version = config('facebook.api_version', 'v25.0');

        try {
            $response = Http::timeout(30)->get("{$base}/{$version}/{$pageId}/posts", [
                'fields' => 'id,message,created_time,permalink_url,status_type,'
                    .'shares,likes.summary(true),comments.summary(true)',
                'limit' => $limit,
                'access_token' => (string) $this->account->access_token,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Facebook fetchRecentMedia failed', ['error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $items = [];
        foreach ((array) $response->json('data', []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $likes = (int) (data_get($row, 'likes.summary.total_count') ?? 0);
            $comments = (int) (data_get($row, 'comments.summary.total_count') ?? 0);
            $shares = (int) (data_get($row, 'shares.count') ?? 0);
            $createdTime = isset($row['created_time']) ? Carbon::parse($row['created_time']) : null;

            $items[] = new SocialMediaItemDTO(
                externalId: (string) ($row['id'] ?? ''),
                provider: 'facebook',
                postType: $this->normalizeType((string) ($row['status_type'] ?? '')),
                caption: isset($row['message']) ? (string) $row['message'] : null,
                permalink: isset($row['permalink_url']) ? (string) $row['permalink_url'] : null,
                publishedAt: $createdTime,
                likes: $likes,
                comments: $comments,
                shares: $shares,
                reach: 0,
                impressions: 0,
                raw: $row,
            );
        }

        return $items;
    }

    public function fetchInsights(): SocialInsightsDTO
    {
        $pageId = $this->pageId();
        $base = rtrim((string) config('facebook.graph_base_url'), '/');
        $version = config('facebook.api_version', 'v25.0');
        $token = (string) $this->account->access_token;

        $impressions = 0;
        $engagement = 0;
        $followers = 0;

        try {
            $insights = Http::timeout(30)->get("{$base}/{$version}/{$pageId}/insights", [
                'metric' => 'page_impressions,page_post_engagements,page_fans',
                'period' => 'day',
                'access_token' => $token,
            ]);

            foreach ((array) $insights->json('data', []) as $metric) {
                $name = (string) ($metric['name'] ?? '');
                $value = (int) (data_get($metric, 'values.0.value') ?? 0);
                match ($name) {
                    'page_impressions' => $impressions = $value,
                    'page_post_engagements' => $engagement = $value,
                    'page_fans' => $followers = $value,
                    default => null,
                };
            }
        } catch (\Throwable $e) {
            Log::warning('Facebook fetchInsights failed', ['error' => $e->getMessage()]);
        }

        return new SocialInsightsDTO(
            followers: $followers,
            reach: $engagement,
            impressions: $impressions,
            profileViews: 0,
            period: 'day',
        );
    }

    private function pageId(): string
    {
        $metadata = $this->account->metadata ?? [];

        return (string) ($metadata['page_id'] ?? $this->account->provider_account_id ?? '');
    }

    private function normalizeType(string $statusType): string
    {
        return match (strtolower($statusType)) {
            'added_video' => 'video',
            'added_photos' => 'image',
            'shared_story' => 'text',
            default => 'text',
        };
    }

    /**
     * @return list<string>
     */
    public static function capabilities(): array
    {
        return config('social_providers.capabilities.facebook', [
            'publish',
            'validate_account',
        ]);
    }
}
