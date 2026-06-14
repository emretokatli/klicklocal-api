<?php

namespace App\Services\Trends\Fake;

use App\Models\TrendAudio;
use App\Models\TrendFormat;
use App\Models\TrendHashtag;
use App\Models\TrendTopic;
use App\Services\Trends\Contracts\TrendProviderInterface;
use App\Services\Trends\Exceptions\TrendProviderException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Simulated trend provider for local German businesses.
 *
 * Reads the seeded trend_* tables (see TrendSeeder). When a table has no seeded
 * rows it falls back to in-memory placeholder models so callers always receive
 * deterministic, German-market content during local development and tests.
 */
class FakeTrendProvider implements TrendProviderInterface
{
    public const DRIVER = 'fake';

    public function driver(): string
    {
        return self::DRIVER;
    }

    /**
     * @return Collection<int, TrendTopic>
     */
    public function topics(?string $category = null, int $limit = 20): Collection
    {
        $this->ensureCapability('topics');

        $query = TrendTopic::query()
            ->where('source', self::DRIVER)
            ->orderByDesc('score');

        if ($category !== null) {
            $query->where('category', $category);
        }

        $topics = $query->limit($limit)->get();

        if ($topics->isNotEmpty()) {
            return $topics;
        }

        return $this->placeholderTopics($category)->take($limit)->values();
    }

    /**
     * @return Collection<int, TrendHashtag>
     */
    public function hashtags(?string $category = null, int $limit = 20): Collection
    {
        $this->ensureCapability('hashtags');

        $query = TrendHashtag::query()
            ->where('source', self::DRIVER)
            ->orderBy('id');

        if ($category !== null) {
            $query->where('category', $category);
        }

        $hashtags = $query->limit($limit)->get();

        if ($hashtags->isNotEmpty()) {
            return $hashtags;
        }

        return $this->placeholderHashtags($category)->take($limit)->values();
    }

    /**
     * @return Collection<int, TrendAudio>
     */
    public function audio(?string $platform = null, int $limit = 20): Collection
    {
        $this->ensureCapability('audio');

        $query = TrendAudio::query()
            ->where('source', self::DRIVER)
            ->orderBy('id');

        if ($platform !== null) {
            $query->where('platform', $platform);
        }

        $audio = $query->limit($limit)->get();

        if ($audio->isNotEmpty()) {
            return $audio;
        }

        return $this->placeholderAudio($platform)->take($limit)->values();
    }

    /**
     * @return Collection<int, TrendFormat>
     */
    public function formats(?string $platform = null, int $limit = 20): Collection
    {
        $this->ensureCapability('formats');

        $query = TrendFormat::query()
            ->where('source', self::DRIVER)
            ->orderBy('id');

        if ($platform !== null) {
            $query->where('platform', $platform);
        }

        $formats = $query->limit($limit)->get();

        if ($formats->isNotEmpty()) {
            return $formats;
        }

        return $this->placeholderFormats($platform)->take($limit)->values();
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, static::capabilities(), true);
    }

    /**
     * @return list<string>
     */
    public static function capabilities(): array
    {
        return config('trends.capabilities.fake', ['topics', 'hashtags', 'audio', 'formats']);
    }

    private function ensureCapability(string $capability): void
    {
        if (! $this->supports($capability)) {
            throw TrendProviderException::capabilityNotSupported(self::DRIVER, $capability);
        }
    }

    /**
     * @return Collection<int, TrendTopic>
     */
    private function placeholderTopics(?string $category): Collection
    {
        $now = Carbon::now();

        $rows = [
            ['title' => 'Regionale Zutaten im Rampenlicht', 'category' => 'gastronomie', 'score' => 92],
            ['title' => 'Vorher-Nachher aus dem Salon', 'category' => 'beauty', 'score' => 88],
            ['title' => 'Handwerk live: Projekt von der Skizze zur Fertigstellung', 'category' => 'handwerk', 'score' => 81],
            ['title' => 'Tag im Leben eines Familienbetriebs', 'category' => 'dienstleistung', 'score' => 79],
            ['title' => 'Saisonales Angebot der Woche', 'category' => 'einzelhandel', 'score' => 74],
            ['title' => 'Mini-Workout für zwischendurch', 'category' => 'fitness', 'score' => 70],
        ];

        return collect($rows)
            ->when($category !== null, fn ($c) => $c->where('category', $category))
            ->map(fn (array $row) => new TrendTopic([
                'title' => $row['title'],
                'description' => 'Platzhalter-Trend für lokale Betriebe in Deutschland.',
                'category' => $row['category'],
                'score' => $row['score'],
                'source' => self::DRIVER,
                'valid_from' => $now->copy()->subDays(2),
                'valid_until' => $now->copy()->addDays(14),
                'raw_payload' => ['simulated' => true, 'placeholder' => true, 'region' => 'DE'],
            ]))
            ->values();
    }

    /**
     * @return Collection<int, TrendHashtag>
     */
    private function placeholderHashtags(?string $category): Collection
    {
        $rows = [
            ['tag' => '#regionalgenuss', 'category' => 'gastronomie', 'volume_label' => 'hoch'],
            ['tag' => '#ausmeinerstadt', 'category' => 'dienstleistung', 'volume_label' => 'viral'],
            ['tag' => '#unterstützelokal', 'category' => 'einzelhandel', 'volume_label' => 'hoch'],
            ['tag' => '#handgemacht', 'category' => 'handwerk', 'volume_label' => 'mittel'],
            ['tag' => '#vorhernachher', 'category' => 'beauty', 'volume_label' => 'viral'],
            ['tag' => '#fitnessmotivation', 'category' => 'fitness', 'volume_label' => 'hoch'],
        ];

        return collect($rows)
            ->when($category !== null, fn ($c) => $c->where('category', $category))
            ->map(fn (array $row) => new TrendHashtag([
                'tag' => $row['tag'],
                'category' => $row['category'],
                'volume_label' => $row['volume_label'],
                'source' => self::DRIVER,
            ]))
            ->values();
    }

    /**
     * @return Collection<int, TrendAudio>
     */
    private function placeholderAudio(?string $platform): Collection
    {
        $rows = [
            ['name' => 'Sommer-Vibes (Trending Sound)', 'platform' => 'instagram', 'external_ref' => 'instagram_audio_482910'],
            ['name' => 'Aufbau-Beat für Vorher-Nachher', 'platform' => 'tiktok', 'external_ref' => 'tiktok_audio_771203'],
            ['name' => 'Ruhiger Lo-Fi Hintergrund', 'platform' => 'instagram', 'external_ref' => 'instagram_audio_339005'],
            ['name' => 'Energiegeladener Pop-Hook', 'platform' => 'tiktok', 'external_ref' => 'tiktok_audio_905512'],
        ];

        return collect($rows)
            ->when($platform !== null, fn ($c) => $c->where('platform', $platform))
            ->map(fn (array $row) => new TrendAudio([
                'name' => $row['name'],
                'platform' => $row['platform'],
                'external_ref' => $row['external_ref'],
                'source' => self::DRIVER,
            ]))
            ->values();
    }

    /**
     * @return Collection<int, TrendFormat>
     */
    private function placeholderFormats(?string $platform): Collection
    {
        $rows = [
            ['name' => 'Vorher-Nachher', 'platform' => 'reels', 'description' => 'Zwei-Shot-Transformation mit hartem Schnitt auf den Beat.'],
            ['name' => 'Tag im Leben', 'platform' => 'tiktok', 'description' => 'Chronologische Mini-Vlog-Sequenz vom Öffnen bis Schließen.'],
            ['name' => 'Produkt-Storytelling', 'platform' => 'instagram', 'description' => 'Ein Produkt von Herkunft bis Anwendung in vier Szenen erzählt.'],
            ['name' => 'Kunden-Testimonial', 'platform' => 'reels', 'description' => 'Echte Stimme der Kundschaft über O-Ton und Untertitel.'],
        ];

        return collect($rows)
            ->when($platform !== null, fn ($c) => $c->where('platform', $platform))
            ->map(fn (array $row) => new TrendFormat([
                'name' => $row['name'],
                'description' => $row['description'],
                'platform' => $row['platform'],
                'source' => self::DRIVER,
            ]))
            ->values();
    }
}
