<?php

namespace App\Services\SocialProviders\TikTok;

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
 * TikTok Content Posting API provider (direct post, PULL_FROM_URL flow).
 */
class TikTokProvider extends BaseSocialProvider implements AnalyzesContent
{
    public function __construct(
        SocialAccount $account,
        private readonly TikTokOAuthService $oauth,
        private readonly TikTokPublishingService $publishing,
    ) {
        parent::__construct($account);
    }

    public function platform(): string
    {
        return 'tiktok';
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
        $token = (string) $this->account->access_token;
        $url = (string) config('tiktok.video_list_url');

        try {
            $response = Http::withToken($token)
                ->timeout(30)
                ->asJson()
                ->post($url.'?fields=id,title,create_time,like_count,comment_count,share_count,view_count,share_url', [
                    'max_count' => min($limit, 20),
                ]);
        } catch (\Throwable $e) {
            Log::warning('TikTok fetchRecentMedia failed', ['error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $items = [];
        foreach ((array) $response->json('data.videos', []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $createTime = isset($row['create_time'])
                ? Carbon::createFromTimestamp((int) $row['create_time'])
                : null;

            $likes = (int) ($row['like_count'] ?? 0);
            $comments = (int) ($row['comment_count'] ?? 0);
            $shares = (int) ($row['share_count'] ?? 0);
            $views = (int) ($row['view_count'] ?? 0);

            $items[] = new SocialMediaItemDTO(
                externalId: (string) ($row['id'] ?? ''),
                provider: 'tiktok',
                postType: 'video',
                caption: isset($row['title']) ? (string) $row['title'] : null,
                permalink: isset($row['share_url']) ? (string) $row['share_url'] : null,
                publishedAt: $createTime,
                likes: $likes,
                comments: $comments,
                shares: $shares,
                reach: $views,
                impressions: $views,
                raw: $row,
            );
        }

        return $items;
    }

    public function fetchInsights(): SocialInsightsDTO
    {
        // TikTok has no aggregate account-insights endpoint on the Display API;
        // derive totals from the recent video list.
        $media = $this->fetchRecentMedia(20);

        $views = array_sum(array_map(static fn (SocialMediaItemDTO $m): int => $m->reach, $media));
        $engagement = array_sum(array_map(static fn (SocialMediaItemDTO $m): int => $m->engagement(), $media));

        return new SocialInsightsDTO(
            followers: 0,
            reach: $views,
            impressions: $views,
            profileViews: $engagement,
            period: 'recent',
        );
    }

    /**
     * @return list<string>
     */
    public static function capabilities(): array
    {
        return config('social_providers.capabilities.tiktok', [
            'publish',
            'refresh_token',
            'validate_account',
        ]);
    }
}
