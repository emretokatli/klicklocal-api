<?php

namespace App\Services\SocialProviders\Instagram;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\SocialProviders\Base\BaseSocialProvider;
use App\Services\SocialProviders\Contracts\AnalyzesContent;
use App\Services\SocialProviders\Contracts\FetchesComments;
use App\Services\SocialProviders\DTOs\CommentCollectionDTO;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;
use App\Services\SocialProviders\DTOs\SocialInsightsDTO;
use App\Services\SocialProviders\DTOs\SocialMediaItemDTO;
use App\Services\SocialProviders\Exceptions\SocialProviderException;
use App\Enums\SocialAccountStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Instagram Platform API provider (Business Login).
 * Feed image publishing via InstagramPublishingService. Reels/stories: future.
 */
class InstagramProvider extends BaseSocialProvider implements FetchesComments, AnalyzesContent
{
    public function __construct(
        SocialAccount $account,
        private readonly InstagramOAuthService $oauth,
        private readonly InstagramService $instagramService,
        private readonly InstagramPublishingService $publishing,
        private readonly InstagramCommentService $comments,
    ) {
        parent::__construct($account);
    }

    public function platform(): string
    {
        return 'instagram';
    }

    public function publish(Post $post): PublishResponseDTO
    {
        $this->ensureCapability('publish');
        $this->ensureValidAccount();

        $post->loadMissing('media');

        return $this->publishing->publishPost($this->account, $post);
    }

    public function fetchComments(
        SocialAccount $account,
        string $providerMediaId,
        ?string $since = null,
    ): CommentCollectionDTO {
        $this->ensureCapability('fetch_comments');

        return $this->comments->fetchComments($account, $providerMediaId, $since);
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

    public function refreshToken(): SocialAccount
    {
        $this->ensureCapability('refresh_token');

        return $this->instagramService->refreshToken($this->account);
    }

    /**
     * @return list<SocialMediaItemDTO>
     */
    public function fetchRecentMedia(int $limit = 25): array
    {
        $igUserId = $this->igUserId();
        $base = rtrim((string) config('instagram.graph_base_url'), '/');
        $version = config('instagram.api_version', 'v21.0');

        try {
            $response = Http::timeout(30)->get("{$base}/{$version}/{$igUserId}/media", [
                'fields' => 'id,caption,media_type,media_product_type,permalink,timestamp,like_count,comments_count',
                'limit' => $limit,
                'access_token' => (string) $this->account->access_token,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Instagram fetchRecentMedia failed', ['error' => $e->getMessage()]);

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

            $likes = (int) ($row['like_count'] ?? 0);
            $comments = (int) ($row['comments_count'] ?? 0);
            $timestamp = isset($row['timestamp']) ? Carbon::parse($row['timestamp']) : null;

            $items[] = new SocialMediaItemDTO(
                externalId: (string) ($row['id'] ?? ''),
                provider: 'instagram',
                postType: $this->normalizeType($row),
                caption: isset($row['caption']) ? (string) $row['caption'] : null,
                permalink: isset($row['permalink']) ? (string) $row['permalink'] : null,
                publishedAt: $timestamp,
                likes: $likes,
                comments: $comments,
                shares: 0,
                reach: 0,
                impressions: 0,
                raw: $row,
            );
        }

        return $items;
    }

    public function fetchInsights(): SocialInsightsDTO
    {
        $igUserId = $this->igUserId();
        $base = rtrim((string) config('instagram.graph_base_url'), '/');
        $version = config('instagram.api_version', 'v21.0');
        $token = (string) $this->account->access_token;

        $reach = 0;
        $impressions = 0;
        $profileViews = 0;
        $followers = 0;

        try {
            $insights = Http::timeout(30)->get("{$base}/{$version}/{$igUserId}/insights", [
                'metric' => 'reach,impressions,profile_views',
                'period' => 'day',
                'access_token' => $token,
            ]);

            foreach ((array) $insights->json('data', []) as $metric) {
                $name = (string) ($metric['name'] ?? '');
                $value = (int) (data_get($metric, 'values.0.value') ?? 0);
                match ($name) {
                    'reach' => $reach = $value,
                    'impressions' => $impressions = $value,
                    'profile_views' => $profileViews = $value,
                    default => null,
                };
            }

            $profile = Http::timeout(30)->get("{$base}/{$version}/{$igUserId}", [
                'fields' => 'followers_count',
                'access_token' => $token,
            ]);
            $followers = (int) ($profile->json('followers_count') ?? 0);
        } catch (\Throwable $e) {
            Log::warning('Instagram fetchInsights failed', ['error' => $e->getMessage()]);
        }

        return new SocialInsightsDTO(
            followers: $followers,
            reach: $reach,
            impressions: $impressions,
            profileViews: $profileViews,
            period: 'day',
        );
    }

    private function igUserId(): string
    {
        $metadata = $this->account->metadata ?? [];

        return (string) ($metadata['instagram_user_id'] ?? $this->account->provider_account_id ?? '');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function normalizeType(array $row): string
    {
        if (($row['media_product_type'] ?? '') === 'REELS') {
            return 'reel';
        }

        return match ((string) ($row['media_type'] ?? '')) {
            'VIDEO' => 'video',
            'CAROUSEL_ALBUM' => 'carousel',
            default => 'image',
        };
    }

    /**
     * @return list<string>
     */
    public static function capabilities(): array
    {
        return config('social_providers.capabilities.instagram', [
            'publish',
            'refresh_token',
            'validate_account',
        ]);
    }
}
