<?php

namespace App\Enums;

enum PlanFeature: string
{
    case MaxWorkspaces = 'max_workspaces';
    case MaxSocialAccounts = 'max_social_accounts';
    case MaxTeamMembers = 'max_team_members';
    case AiGeneration = 'ai_generation';
    case AiMonthlyTokens = 'ai_monthly_tokens';
    case AnalyticsEnabled = 'analytics_enabled';
    case VideoGeneration = 'video_generation';
    case StorageLimitMb = 'storage_limit_mb';
    case ScheduledPostsMonthly = 'scheduled_posts_monthly';
    case MediaUploadsMonthly = 'media_uploads_monthly';
    case ApiCallsMonthly = 'api_calls_monthly';

    public function isBoolean(): bool
    {
        return in_array($this, [
            self::AiGeneration,
            self::AnalyticsEnabled,
            self::VideoGeneration,
        ], true);
    }

    public function isUnlimited(int $value): bool
    {
        return $value < 0;
    }

    /** @return list<string> */
    public static function meteredKeys(): array
    {
        return [
            self::AiMonthlyTokens->value,
            self::StorageLimitMb->value,
            self::ScheduledPostsMonthly->value,
            self::MediaUploadsMonthly->value,
            self::ApiCallsMonthly->value,
        ];
    }
}
