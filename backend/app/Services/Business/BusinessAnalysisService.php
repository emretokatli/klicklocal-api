<?php

namespace App\Services\Business;

use App\Models\BusinessProfile;
use App\Models\Workspace;
use App\Services\Ai\DTOs\BusinessWebsiteAnalysisDTO;
use App\Services\Ai\WebsiteAnalysisService;
use App\Services\Billing\WorkspaceSubscriptionService;

/**
 * Resolves the cached (or freshly generated) website analysis for a workspace
 * and shapes it into the correct teaser/full tier.
 *
 * The subscription tier is decided here, server-side: an unsubscribed workspace
 * only ever receives the teaser payload (score, summary, brand tone, and the
 * counts of strengths/weaknesses) — the full lists are never serialized for it.
 */
class BusinessAnalysisService
{
    public function __construct(
        private readonly WebsiteAnalysisService $analyzer,
        private readonly WorkspaceSubscriptionService $subscriptions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forWorkspace(Workspace $workspace, bool $refresh = false): array
    {
        $subscribed = $this->subscriptions->activeForWorkspace($workspace) !== null;
        $tier = $subscribed ? 'full' : 'teaser';

        $profile = $workspace->businessProfile;

        if ($profile === null || blank($profile->website)) {
            return [
                'available' => false,
                'tier' => $tier,
                'website' => $profile?->website,
                'analyzed_at' => null,
                'analysis' => null,
            ];
        }

        $dto = $this->resolveAnalysis($profile, $refresh);

        return [
            'available' => true,
            'tier' => $tier,
            'website' => $profile->website,
            'analyzed_at' => $profile->website_analyzed_at?->toIso8601String(),
            'analysis' => $subscribed ? $dto->toFullTier() : $dto->toTeaserTier(),
        ];
    }

    /**
     * Reuse the persisted analysis unless it is missing, stale (the website
     * changed), or a refresh was explicitly requested.
     */
    private function resolveAnalysis(BusinessProfile $profile, bool $refresh): BusinessWebsiteAnalysisDTO
    {
        $cached = is_array($profile->website_analysis) ? $profile->website_analysis : null;
        $isFresh = $cached !== null
            && $profile->website_analysis_url === $profile->website
            && ! $refresh;

        if ($isFresh) {
            return BusinessWebsiteAnalysisDTO::fromArray($cached);
        }

        $dto = $this->analyzer->analyzeBusiness([
            'website' => $profile->website,
            'business_name' => $profile->business_name,
            'industry' => $profile->business_type,
        ]);

        $profile->forceFill([
            'website_analysis' => $dto->toArray(),
            'website_analysis_url' => $profile->website,
            'website_analyzed_at' => now(),
        ])->save();

        return $dto;
    }
}
