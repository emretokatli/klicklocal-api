<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\SerpSearchClientInterface;
use App\Services\Ai\Contracts\WebAnalyzeClientInterface;
use App\Services\Ai\DTOs\WebAnalyzeResultDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Code-first WebAnalyze driver: gathers every signal in PHP (WebsiteDataCollector,
 * SerpApiSearchClient, SocialProfileFetcher), then makes a SINGLE Anthropic call
 * to synthesise the report. ~$0.03/run vs the agent runner's ~$0.50. Results are
 * cached per-URL so repeat lookups within the TTL cost nothing.
 */
class CodeFirstWebAnalyzeClient implements WebAnalyzeClientInterface
{
    public function __construct(
        private readonly WebsiteDataCollector $dataCollector,
        private readonly SerpSearchClientInterface $searchClient,
        private readonly SocialProfileFetcher $socialFetcher,
        private readonly WebAnalyzeReportGenerator $generator,
    ) {}

    public function analyze(string $website): WebAnalyzeResultDTO
    {
        $ttlHours = (int) config('webanalyze.cache_ttl_hours', 168);

        if ($ttlHours <= 0) {
            return $this->runFresh($website);
        }

        $cacheKey = $this->cacheKey($website);
        $store = Cache::driver((string) config('webanalyze.cache_driver', 'redis'));

        $wasCached = $store->has($cacheKey);

        $result = $store->remember(
            $cacheKey,
            now()->addHours($ttlHours),
            fn (): WebAnalyzeResultDTO => $this->runFresh($website),
        );

        return $wasCached ? $this->withCachedFlag($result) : $result;
    }

    public function cacheKey(string $website): string
    {
        return 'webanalyze:v2:'.md5(strtolower(trim($website)));
    }

    private function runFresh(string $website): WebAnalyzeResultDTO
    {
        $startedAt = microtime(true);

        $siteData = $this->dataCollector->collect($website);

        $businessName = $this->guessBusinessName($siteData);
        $businessType = $this->guessBusinessType($siteData);
        $city = $this->guessCity($siteData);

        $categoryQuery = trim($businessType.' '.$city) ?: $website;
        $businessQuery = $businessName !== ''
            ? '"'.$businessName.'" '.$city
            : $categoryQuery;

        $serpCompetitors = $this->searchClient->search($categoryQuery);
        $serpBusiness = $this->searchClient->search(trim($businessQuery));

        $socialProfiles = $this->collectSocialProfiles($siteData['social_links'] ?? []);

        $payload = $siteData;
        $payload['input_website'] = $website;
        $payload['business_name_guess'] = $businessName;
        $payload['business_type_guess'] = $businessType;
        $payload['city_guess'] = $city;
        $payload['serp_category_search'] = ['query' => $categoryQuery] + $serpCompetitors;
        $payload['serp_business_search'] = ['query' => trim($businessQuery)] + $serpBusiness;
        $payload['social_profiles'] = $socialProfiles;

        $report = $this->generator->generate($payload);

        [$score, $band] = WebAnalyzeReportParser::parseScore($report['markdown']);

        return new WebAnalyzeResultDTO(
            website: $website,
            reportMarkdown: $report['markdown'],
            score: $score,
            band: $band,
            sessionId: null,
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
            model: $report['model'],
            errors: [],
            totalCostUsd: $report['total_cost_usd'],
            numTurns: 1,
            cached: false,
        );
    }

    /**
     * @param  string[]  $socialLinks
     * @return array<int, array<string, mixed>>
     */
    private function collectSocialProfiles(array $socialLinks): array
    {
        $profiles = [];

        foreach ($socialLinks as $link) {
            $lower = Str::lower($link);

            if (Str::contains($lower, 'instagram.com')) {
                $profiles[] = ['platform' => 'instagram'] + $this->socialFetcher->fetchInstagram($link);
            } elseif (Str::contains($lower, 'facebook.com')) {
                $profiles[] = ['platform' => 'facebook'] + $this->socialFetcher->fetchFacebook($link);
            } else {
                $profiles[] = ['platform' => $this->platformName($lower), 'url' => $link, 'exists' => true];
            }
        }

        return $profiles;
    }

    private function platformName(string $url): string
    {
        foreach (['tiktok', 'linkedin', 'youtube', 'xing', 'twitter', 'x.com'] as $name) {
            if (Str::contains($url, $name)) {
                return $name === 'x.com' ? 'x' : $name;
            }
        }

        return 'other';
    }

    /**
     * @param  array<string, mixed>  $siteData
     */
    private function guessBusinessName(array $siteData): string
    {
        foreach ([$siteData['og_title'] ?? null, $siteData['title'] ?? null] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                // Trim common "Name | Slogan" / "Name - Slogan" suffixes.
                return trim(preg_split('/\s[|\-–—]\s/u', $candidate)[0] ?? $candidate);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $siteData
     */
    private function guessBusinessType(array $siteData): string
    {
        foreach ((array) ($siteData['schema_types'] ?? []) as $type) {
            if (is_string($type) && ! in_array($type, ['Organization', 'WebSite', 'WebPage', 'BreadcrumbList'], true)) {
                return $type;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $siteData
     */
    private function guessCity(array $siteData): string
    {
        foreach ((array) ($siteData['addresses'] ?? []) as $address) {
            if (is_string($address) && preg_match('/\d{5}\s+([A-ZÄÖÜ][\wäöüß\- ]+)/u', $address, $m) === 1) {
                return trim($m[1]);
            }
        }

        return '';
    }

    private function withCachedFlag(WebAnalyzeResultDTO $result): WebAnalyzeResultDTO
    {
        return new WebAnalyzeResultDTO(
            website: $result->website,
            reportMarkdown: $result->reportMarkdown,
            score: $result->score,
            band: $result->band,
            sessionId: $result->sessionId,
            durationMs: $result->durationMs,
            model: $result->model,
            errors: $result->errors,
            totalCostUsd: $result->totalCostUsd,
            numTurns: $result->numTurns,
            cached: true,
        );
    }
}
