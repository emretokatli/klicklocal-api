<?php

namespace App\Services\Analysis;

use App\Enums\SocialAccountStatus;
use App\Models\SocialAccount;
use App\Models\SocialContentAnalysis;
use App\Models\Workspace;
use App\Services\Ai\Contracts\OpenAiClientInterface;
use App\Services\Ai\DTOs\ContentPlanSuggestionDTO;
use App\Services\SocialProviders\Contracts\AnalyzesContent;
use App\Services\SocialProviders\Factory\SocialProviderFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Fetches recent media + insights from each connected social provider,
 * normalizes them into the social_content_analyses table, and turns the
 * aggregated data into an AI content-plan suggestion.
 */
class SocialContentAnalysisService
{
    public function __construct(
        private readonly SocialProviderFactory $providerFactory,
        private readonly OpenAiClientInterface $ai,
    ) {}

    /**
     * Pull recent media + insights for every connected account and persist the
     * normalized media rows.
     *
     * @return array{synced: int, accounts: list<array<string, mixed>>}
     */
    public function sync(Workspace $workspace): array
    {
        $accounts = SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', SocialAccountStatus::Connected)
            ->get();

        $synced = 0;
        $accountSummaries = [];

        foreach ($accounts as $account) {
            $provider = $this->providerFactory->make($account->provider, $account);

            if (! $provider instanceof AnalyzesContent) {
                continue;
            }

            try {
                $media = $provider->fetchRecentMedia(25);
                $insights = $provider->fetchInsights();
            } catch (\Throwable $e) {
                Log::warning('Social content analysis sync failed for account', [
                    'social_account_id' => $account->id,
                    'provider' => $account->provider,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($media as $item) {
                if ($item->externalId === '') {
                    continue;
                }

                SocialContentAnalysis::query()->updateOrCreate(
                    [
                        'social_account_id' => $account->id,
                        'external_id' => $item->externalId,
                    ],
                    [
                        'workspace_id' => $workspace->id,
                        'provider' => $item->provider,
                        'post_type' => $item->postType,
                        'caption' => $item->caption,
                        'permalink' => $item->permalink,
                        'published_at' => $item->publishedAt,
                        'hour' => $item->hour(),
                        'likes' => $item->likes,
                        'comments' => $item->comments,
                        'shares' => $item->shares,
                        'reach' => $item->reach,
                        'impressions' => $item->impressions,
                        'engagement' => $item->engagement(),
                        'raw' => $item->raw,
                    ],
                );

                $synced++;
            }

            $accountSummaries[] = [
                'social_account_id' => $account->id,
                'provider' => $account->provider,
                'media_count' => count($media),
                'insights' => $insights->toArray(),
            ];
        }

        return ['synced' => $synced, 'accounts' => $accountSummaries];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, SocialContentAnalysis>
     */
    public function list(Workspace $workspace, int $limit = 100)
    {
        return SocialContentAnalysis::query()
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    public function suggestContentPlan(Workspace $workspace): ContentPlanSuggestionDTO
    {
        $rows = $this->list($workspace, 100);

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'analysis' => ['No analyzed content yet. Run a sync first.'],
            ]);
        }

        $analytics = $this->aggregate($rows);
        $context = $this->context($workspace);

        return $this->ai->suggestContentPlan($this->systemPrompt(), $analytics, $context);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SocialContentAnalysis>  $rows
     * @return array<string, mixed>
     */
    private function aggregate($rows): array
    {
        $byType = [];
        $byHour = [];

        foreach ($rows as $row) {
            $type = $row->post_type ?: 'unknown';
            $byType[$type] ??= ['count' => 0, 'engagement' => 0, 'reach' => 0];
            $byType[$type]['count']++;
            $byType[$type]['engagement'] += $row->engagement;
            $byType[$type]['reach'] += $row->reach;

            if ($row->hour !== null) {
                $byHour[$row->hour] ??= ['count' => 0, 'engagement' => 0];
                $byHour[$row->hour]['count']++;
                $byHour[$row->hour]['engagement'] += $row->engagement;
            }
        }

        $typeSummary = [];
        foreach ($byType as $type => $data) {
            $typeSummary[$type] = [
                'count' => $data['count'],
                'avg_engagement' => (int) round($data['engagement'] / max(1, $data['count'])),
                'total_reach' => $data['reach'],
            ];
        }

        $hourSummary = [];
        foreach ($byHour as $hour => $data) {
            $hourSummary[$hour] = [
                'count' => $data['count'],
                'avg_engagement' => (int) round($data['engagement'] / max(1, $data['count'])),
            ];
        }

        // Best performing type / hour by average engagement.
        $topPostType = $this->keyWithMaxAvg($typeSummary);
        $bestHour = $this->keyWithMaxAvg($hourSummary);

        $topPosts = $rows
            ->sortByDesc('engagement')
            ->take(5)
            ->map(fn (SocialContentAnalysis $r): array => [
                'provider' => $r->provider,
                'post_type' => $r->post_type,
                'hour' => $r->hour,
                'engagement' => $r->engagement,
                'reach' => $r->reach,
                'caption' => $r->caption,
            ])
            ->values()
            ->all();

        return [
            'total_posts' => $rows->count(),
            'by_post_type' => $typeSummary,
            'by_hour' => $hourSummary,
            'top_posts' => $topPosts,
            'top_post_type' => $topPostType,
            'best_hour' => $bestHour !== null ? (int) $bestHour : 18,
        ];
    }

    /**
     * @param  array<int|string, array{avg_engagement: int, count?: int}>  $summary
     */
    private function keyWithMaxAvg(array $summary): int|string|null
    {
        $best = null;
        $bestAvg = -1;

        foreach ($summary as $key => $data) {
            if ($data['avg_engagement'] > $bestAvg) {
                $bestAvg = $data['avg_engagement'];
                $best = $key;
            }
        }

        return $best;
    }

    /**
     * @return array<string, string>
     */
    private function context(Workspace $workspace): array
    {
        $profile = $workspace->businessProfile;

        return [
            'business_name' => (string) ($profile->business_name ?? $workspace->name),
            'business_type' => (string) ($profile->business_type ?? ''),
            'city' => (string) ($profile->city ?? ''),
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are a social media strategist for local German businesses. You receive
        normalized analytics (engagement per post type, per hour, top posts, reach).
        Derive a concrete, actionable content plan. Respond ONLY with valid JSON:
        {
          "summary": "2-3 Sätze Zusammenfassung der Erkenntnisse (Deutsch)",
          "best_times": ["z.B. 'Werktags 18:00 Uhr'", "..."],
          "recommended_post_types": ["Reel", "Karussell", "..."],
          "content_ideas": [
            {"title": "Kurztitel", "format": "Reel|Karussell|Story|Bild", "reason": "Warum (Deutsch)"}
          ]
        }
        Write all text in German. Base your advice on the data; be specific and practical.
        PROMPT;
    }
}
