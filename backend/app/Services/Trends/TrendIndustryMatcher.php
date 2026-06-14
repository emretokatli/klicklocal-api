<?php

namespace App\Services\Trends;

use App\Models\TrendTopic;
use App\Services\Ai\Contracts\OpenAiClientInterface;
use App\Services\Trends\DTOs\MatchedTrendDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Decides which trends fit a business's industry and attaches a short AI comment
 * and a suggested content format to each.
 *
 * Fit is deterministic: the free-text business_type (e.g. "Bäckerei", "Anwalt")
 * is resolved to one of the seeded trend categories via keyword matching; a
 * trend fits when its category matches. A non-empty but unrecognised type falls
 * back to the general "dienstleistung" category, so e.g. food/beauty trends do
 * not fit a lawyer while general service trends still can.
 */
class TrendIndustryMatcher
{
    /**
     * Keyword (substring of the lowercased business_type) → trend category.
     * Order matters only for readability; first match wins.
     *
     * @var array<string, string>
     */
    private const KEYWORD_CATEGORY = [
        // gastronomie
        'bäckerei' => 'gastronomie',
        'baeckerei' => 'gastronomie',
        'konditorei' => 'gastronomie',
        'café' => 'gastronomie',
        'cafe' => 'gastronomie',
        'restaurant' => 'gastronomie',
        'gastronomie' => 'gastronomie',
        'imbiss' => 'gastronomie',
        'pizzeria' => 'gastronomie',
        'metzgerei' => 'gastronomie',
        'eisdiele' => 'gastronomie',
        'bar' => 'gastronomie',
        'catering' => 'gastronomie',
        'koch' => 'gastronomie',
        // handwerk
        'handwerk' => 'handwerk',
        'tischler' => 'handwerk',
        'schreiner' => 'handwerk',
        'maler' => 'handwerk',
        'dachdecker' => 'handwerk',
        'elektriker' => 'handwerk',
        'klempner' => 'handwerk',
        'sanitär' => 'handwerk',
        'bau' => 'handwerk',
        'kfz' => 'handwerk',
        'werkstatt' => 'handwerk',
        'garten' => 'handwerk',
        // einzelhandel
        'einzelhandel' => 'einzelhandel',
        'laden' => 'einzelhandel',
        'boutique' => 'einzelhandel',
        'shop' => 'einzelhandel',
        'geschäft' => 'einzelhandel',
        'buchhandlung' => 'einzelhandel',
        'blumen' => 'einzelhandel',
        'mode' => 'einzelhandel',
        // beauty
        'friseur' => 'beauty',
        'kosmetik' => 'beauty',
        'beauty' => 'beauty',
        'nagel' => 'beauty',
        'salon' => 'beauty',
        'spa' => 'beauty',
        'barbier' => 'beauty',
        'tattoo' => 'beauty',
        // fitness
        'fitness' => 'fitness',
        'gym' => 'fitness',
        'yoga' => 'fitness',
        'sport' => 'fitness',
        'trainer' => 'fitness',
        // dienstleistung (explicit)
        'agentur' => 'dienstleistung',
        'berater' => 'dienstleistung',
        'beratung' => 'dienstleistung',
        'dienstleistung' => 'dienstleistung',
        'anwalt' => 'dienstleistung',
        'kanzlei' => 'dienstleistung',
        'steuerberater' => 'dienstleistung',
        'versicherung' => 'dienstleistung',
        'makler' => 'dienstleistung',
        'praxis' => 'dienstleistung',
        'arzt' => 'dienstleistung',
    ];

    private const DEFAULT_CATEGORY = 'dienstleistung';

    /**
     * Suggested content-format badges (German), picked deterministically per trend.
     *
     * @var list<string>
     */
    private const SUGGESTIONS = [
        'Gut für Reels',
        'Ideal für Stories',
        'Perfekt für einen Post',
        'Top für ein kurzes Video',
    ];

    public function __construct(
        private readonly OpenAiClientInterface $ai,
    ) {}

    /**
     * @param  Collection<int, TrendTopic>  $topics
     * @return list<MatchedTrendDTO>
     */
    public function match(?string $businessType, Collection $topics): array
    {
        $category = $this->categoryFor($businessType);

        // Pre-compute fit so it can be passed to the AI comment generator.
        $withFit = $topics->map(fn (TrendTopic $topic): array => [
            'topic' => $topic,
            'fit' => $this->fits($category, $topic->category),
        ]);

        $comments = $this->comments($businessType, $withFit);

        $matched = $withFit->map(function (array $item) use ($comments, $businessType): MatchedTrendDTO {
            /** @var TrendTopic $topic */
            $topic = $item['topic'];
            $fit = (bool) $item['fit'];

            return new MatchedTrendDTO(
                id: (int) $topic->id,
                title: (string) $topic->title,
                description: $topic->description,
                category: $topic->category,
                score: (int) $topic->score,
                fit: $fit,
                comment: $comments[(int) $topic->id] ?? $this->fallbackComment($topic, $fit, $businessType),
                suggestion: $this->suggestionFor($topic),
            );
        })->all();

        // Fitting trends first, then by score descending.
        usort(
            $matched,
            static fn (MatchedTrendDTO $a, MatchedTrendDTO $b): int => [$b->fit, $b->score] <=> [$a->fit, $a->score],
        );

        return $matched;
    }

    public function categoryFor(?string $businessType): ?string
    {
        $type = Str::lower(trim((string) $businessType));

        if ($type === '') {
            return null;
        }

        foreach (self::KEYWORD_CATEGORY as $keyword => $category) {
            if (str_contains($type, $keyword)) {
                return $category;
            }
        }

        return self::DEFAULT_CATEGORY;
    }

    private function fits(?string $businessCategory, ?string $trendCategory): bool
    {
        return $businessCategory !== null
            && $trendCategory !== null
            && $businessCategory === $trendCategory;
    }

    /**
     * Batched AI comments, resilient: any provider failure degrades to
     * deterministic fallbacks rather than breaking the dashboard.
     *
     * @param  Collection<int, array{topic: TrendTopic, fit: bool}>  $withFit
     * @return array<int, string>
     */
    private function comments(?string $businessType, Collection $withFit): array
    {
        if ($withFit->isEmpty()) {
            return [];
        }

        $trends = [];
        foreach ($withFit as $item) {
            /** @var TrendTopic $topic */
            $topic = $item['topic'];
            $trends[(int) $topic->id] = [
                'title' => (string) $topic->title,
                'description' => (string) $topic->description,
                'fit' => (bool) $item['fit'],
            ];
        }

        $systemPrompt = <<<'PROMPT'
You advise local German businesses on social media. For each trend, write ONE
short, practical comment in German (max 1-2 sentences) explaining whether and how
the business could use it. Respond ONLY with valid JSON:
{"comments": [{"id": <trend id>, "comment": "<German text>"}]}
PROMPT;

        try {
            $comments = $this->ai->commentOnTrends($systemPrompt, $trends, [
                'business_type' => (string) $businessType,
            ]);

            // Drop empty strings so fallbacks fill the gaps.
            return array_filter($comments, static fn (string $c): bool => trim($c) !== '');
        } catch (Throwable) {
            return [];
        }
    }

    private function fallbackComment(TrendTopic $topic, bool $fit, ?string $businessType): string
    {
        $type = trim((string) $businessType) !== '' ? $businessType : 'lokalen Betrieb';

        return $fit
            ? "Dieser Trend passt gut zu deinem {$type}: „{$topic->title}“ lässt sich leicht als kurzes Reel umsetzen."
            : "„{$topic->title}“ ist gerade angesagt, passt aber nur bedingt zu deinem {$type}.";
    }

    private function suggestionFor(TrendTopic $topic): string
    {
        return self::SUGGESTIONS[((int) $topic->id) % count(self::SUGGESTIONS)];
    }
}
