<?php

namespace App\Services\SocialProviders\Contracts;

use App\Services\SocialProviders\DTOs\SocialInsightsDTO;
use App\Services\SocialProviders\DTOs\SocialMediaItemDTO;

/**
 * Optional provider capability: reading recent published media and account-level
 * insights for content analysis. Providers that cannot support it simply do not
 * implement this interface; SocialContentAnalysisService skips them.
 */
interface AnalyzesContent
{
    /**
     * Recent published media for the bound account, normalized.
     *
     * @return list<SocialMediaItemDTO>
     */
    public function fetchRecentMedia(int $limit = 25): array;

    /**
     * Account-level insights (reach, impressions, followers, …) for the period.
     */
    public function fetchInsights(): SocialInsightsDTO;
}
