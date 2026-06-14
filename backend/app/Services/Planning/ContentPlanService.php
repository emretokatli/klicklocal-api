<?php

namespace App\Services\Planning;

use App\Enums\ContentCategory;
use App\Models\Workspace;
use App\Services\Planning\DTOs\PlanSuggestionDTO;
use App\Services\Trends\DTOs\MatchedTrendDTO;
use App\Services\Trends\Factory\TrendProviderFactory;
use App\Services\Trends\TrendIndustryMatcher;
use Illuminate\Support\Carbon;

/**
 * Produces a weekly suggested content plan on top of the existing
 * posts/calendar + PublishPostJob infrastructure. This is purely a planning
 * (suggestion) layer — it does not schedule or publish anything itself.
 *
 * The plan is fed by the workspace's business_profile (name, type, social
 * channels) and its matching trends (via TrendIndustryMatcher, which already
 * routes through OpenAiClientInterface and degrades to deterministic German
 * output under OPENAI_DRIVER=fake).
 */
class ContentPlanService
{
    /** Carbon dayOfWeek (0 = Sunday) → German weekday. */
    private const WEEKDAYS = [
        'Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag',
    ];

    /** @var list<string> */
    private const KNOWN_PLATFORMS = ['instagram', 'tiktok', 'facebook', 'linkedin'];

    public function __construct(
        private readonly TrendProviderFactory $trends,
        private readonly TrendIndustryMatcher $matcher,
    ) {}

    /**
     * @return array{week_start: string, suggestions: list<PlanSuggestionDTO>}
     */
    public function weeklyPlan(Workspace $workspace): array
    {
        $profile = $workspace->businessProfile;
        $businessType = $profile?->business_type;
        $businessName = trim((string) ($profile?->business_name ?? '')) !== ''
            ? (string) $profile->business_name
            : 'dein Betrieb';

        $platforms = $this->platforms(is_array($profile?->social_media_channels) ? $profile->social_media_channels : null);

        // Reuse the trend layer to anchor the "Trend" slot to a real, fitting trend.
        $topics = $this->trends->make()->topics(limit: 20);
        $matched = collect($this->matcher->match($businessType, $topics));
        $topTrend = $matched->firstWhere('fit', true) ?? $matched->first();

        $startOfWeek = Carbon::now()->startOfWeek(); // Monday

        // Spread 4 slots across the week with a fixed category rotation.
        $slots = [
            ['offset' => 0, 'category' => ContentCategory::Angebot],
            ['offset' => 2, 'category' => ContentCategory::BehindTheScenes],
            ['offset' => 4, 'category' => ContentCategory::Trend],
            ['offset' => 6, 'category' => ContentCategory::Lokal],
        ];

        $suggestions = [];

        foreach ($slots as $i => $slot) {
            $date = $startOfWeek->copy()->addDays($slot['offset']);
            /** @var ContentCategory $category */
            $category = $slot['category'];

            $suggestions[] = new PlanSuggestionDTO(
                day: self::WEEKDAYS[$date->dayOfWeek],
                date: $date->toDateString(),
                category: $category->value,
                categoryLabel: $category->label(),
                platform: $platforms[$i % count($platforms)],
                idea: $this->idea($category, $businessName, $topTrend),
                trendTitle: $category === ContentCategory::Trend ? $topTrend?->title : null,
            );
        }

        return [
            'week_start' => $startOfWeek->toDateString(),
            'suggestions' => $suggestions,
        ];
    }

    private function idea(
        ContentCategory $category,
        string $businessName,
        ?MatchedTrendDTO $topTrend,
    ): string {
        return match ($category) {
            ContentCategory::Angebot => "Stelle ein aktuelles Angebot oder Highlight von {$businessName} "
                .'vor – mit klarem Aufruf zum Vorbeikommen oder Buchen.',
            ContentCategory::BehindTheScenes => "Zeig einen Blick hinter die Kulissen von {$businessName}: "
                .'Team, Arbeitsalltag oder die Entstehung eines Produkts.',
            ContentCategory::Trend => $topTrend !== null
                ? "Greif den Trend „{$topTrend->title}“ auf: {$topTrend->comment}"
                : 'Greif einen aktuellen Social-Media-Trend auf und übertrage ihn auf deinen Betrieb.',
            ContentCategory::Lokal => "Zeig deine lokale Verbundenheit – Stadtteil, Stammkund:innen oder "
                ."regionale Partner von {$businessName}.",
        };
    }

    /**
     * @param  list<string>|null  $channels
     * @return list<string>
     */
    private function platforms(?array $channels): array
    {
        $picked = collect($channels ?? [])
            ->map(fn ($c): string => strtolower(trim((string) $c)))
            ->filter(fn (string $c): bool => in_array($c, self::KNOWN_PLATFORMS, true))
            ->unique()
            ->values()
            ->all();

        return $picked !== [] ? $picked : ['instagram'];
    }
}
