<?php

namespace App\Services\SocialProviders\Instagram;

use App\Enums\PostPlatformStatus;
use App\Models\SocialAccount;
use App\Services\SocialProviders\Exceptions\SocialProviderException;
use Illuminate\Support\Facades\DB;

class InstagramPublishingQuotaService
{
    private const MAX_POSTS_PER_24H = 25;

    public function checkQuota(SocialAccount $account): void
    {
        $publishedLast24h = DB::table('post_platforms')
            ->where('social_account_id', $account->id)
            ->where('status', PostPlatformStatus::Published->value)
            ->where('published_at', '>=', now()->subHours(24))
            ->count();

        if ($publishedLast24h >= self::MAX_POSTS_PER_24H) {
            throw SocialProviderException::quotaExceeded(
                'instagram',
                'Maximum of '.self::MAX_POSTS_PER_24H.' posts per 24 hours reached. Please wait before publishing again.',
            );
        }
    }
}
