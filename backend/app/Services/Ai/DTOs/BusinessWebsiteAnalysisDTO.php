<?php

namespace App\Services\Ai\DTOs;

/**
 * Full business website analysis aligned with the klicklocal-webanalyze skill:
 * an overall score (0-100) with a band label, homepage summary, services, a SEO
 * headline assessment, strengths/weaknesses, brand tone, target audience, and a
 * short growth/improvement note.
 *
 * Tiering for the teaser/paywall lives here so the controller can never
 * accidentally leak the full payload to an unsubscribed client: the teaser
 * representation is built from scratch, not by hiding fields client-side.
 */
readonly class BusinessWebsiteAnalysisDTO
{
    /**
     * @param  list<string>  $services
     * @param  list<string>  $strengths
     * @param  list<string>  $weaknesses
     */
    public function __construct(
        public int $score,
        public string $summary,
        public array $services,
        public string $seoAssessment,
        public array $strengths,
        public array $weaknesses,
        public string $brandTone,
        public string $targetAudience,
        public string $growthNote,
        public string $model = '',
        public int $tokensUsed = 0,
    ) {}

    /**
     * Score bands from the klicklocal-webanalyze scoring rubric.
     */
    public static function bandForScore(int $score): string
    {
        return match (true) {
            $score < 40 => 'Kritisch',
            $score < 60 => 'Ausbaufähig',
            $score < 80 => 'Solide',
            default => 'Stark',
        };
    }

    public function band(): string
    {
        return self::bandForScore($this->score);
    }

    /**
     * Full canonical representation — used for persistence and the full tier.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'band' => $this->band(),
            'summary' => $this->summary,
            'services' => $this->services,
            'seo_assessment' => $this->seoAssessment,
            'strengths' => $this->strengths,
            'weaknesses' => $this->weaknesses,
            'brand_tone' => $this->brandTone,
            'target_audience' => $this->targetAudience,
            'growth_note' => $this->growthNote,
        ];
    }

    /**
     * Subscribed users: the complete analysis.
     *
     * @return array<string, mixed>
     */
    public function toFullTier(): array
    {
        return $this->toArray();
    }

    /**
     * Unsubscribed users: ONLY score, summary, brand tone, and the counts of
     * strengths/weaknesses. The full lists, SEO detail, services, target
     * audience and growth note are deliberately omitted from the payload.
     *
     * @return array<string, mixed>
     */
    public function toTeaserTier(): array
    {
        return [
            'score' => $this->score,
            'band' => $this->band(),
            'summary' => $this->summary,
            'brand_tone' => $this->brandTone,
            'strengths_count' => count($this->strengths),
            'weaknesses_count' => count($this->weaknesses),
            'locked_sections' => ['strengths', 'weaknesses', 'seo_assessment', 'services', 'target_audience', 'growth_note'],
        ];
    }

    /**
     * Rehydrate from the persisted JSON (band is recomputed from score).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            score: (int) ($data['score'] ?? 0),
            summary: (string) ($data['summary'] ?? ''),
            services: self::stringList($data['services'] ?? []),
            seoAssessment: (string) ($data['seo_assessment'] ?? ''),
            strengths: self::stringList($data['strengths'] ?? []),
            weaknesses: self::stringList($data['weaknesses'] ?? []),
            brandTone: (string) ($data['brand_tone'] ?? ''),
            targetAudience: (string) ($data['target_audience'] ?? ''),
            growthNote: (string) ($data['growth_note'] ?? ''),
        );
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    public static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item): string => trim((string) $item),
            $value,
        ), static fn (string $item): bool => $item !== ''));
    }
}
