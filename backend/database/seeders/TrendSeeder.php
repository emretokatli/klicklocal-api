<?php

namespace Database\Seeders;

use App\Models\TrendAudio;
use App\Models\TrendFormat;
use App\Models\TrendHashtag;
use App\Models\TrendTopic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds realistic placeholder trend data for the German local-business market.
 *
 * This is the "fake" data layer consumed by FakeTrendProvider until a real
 * trend ingestion ('api') driver is wired up. Idempotent: it clears the
 * trend_* tables before inserting so reseeding stays deterministic.
 */
class TrendSeeder extends Seeder
{
    public function run(): void
    {
        TrendTopic::query()->where('source', 'fake')->delete();
        TrendHashtag::query()->where('source', 'fake')->delete();
        TrendAudio::query()->where('source', 'fake')->delete();
        TrendFormat::query()->where('source', 'fake')->delete();

        $now = Carbon::now();

        foreach ($this->topics() as $topic) {
            TrendTopic::create([
                'title' => $topic['title'],
                'description' => $topic['description'],
                'category' => $topic['category'],
                'score' => $topic['score'],
                'source' => 'fake',
                'valid_from' => $now->copy()->subDays(2),
                'valid_until' => $now->copy()->addDays($topic['valid_days']),
                'raw_payload' => ['simulated' => true, 'region' => 'DE'],
            ]);
        }

        foreach ($this->hashtags() as $hashtag) {
            TrendHashtag::create([
                'tag' => $hashtag['tag'],
                'category' => $hashtag['category'],
                'volume_label' => $hashtag['volume_label'],
                'source' => 'fake',
            ]);
        }

        foreach ($this->audio() as $audio) {
            TrendAudio::create([
                'name' => $audio['name'],
                'platform' => $audio['platform'],
                'external_ref' => $audio['external_ref'],
                'source' => 'fake',
            ]);
        }

        foreach ($this->formats() as $format) {
            TrendFormat::create([
                'name' => $format['name'],
                'description' => $format['description'],
                'platform' => $format['platform'],
                'source' => 'fake',
            ]);
        }
    }

    /**
     * @return list<array{title: string, description: string, category: string, score: int, valid_days: int}>
     */
    private function topics(): array
    {
        return [
            [
                'title' => 'Regionale Zutaten im Rampenlicht',
                'description' => 'Lokale Cafés und Restaurants zeigen, woher ihre Produkte stammen – Bauernhof, Markt und Lieferant im Kurzvideo.',
                'category' => 'gastronomie',
                'score' => 92,
                'valid_days' => 14,
            ],
            [
                'title' => 'Vorher-Nachher aus dem Salon',
                'description' => 'Friseure und Kosmetikstudios setzen auf schnelle Transformationen mit Trending-Sound.',
                'category' => 'beauty',
                'score' => 88,
                'valid_days' => 21,
            ],
            [
                'title' => 'Handwerk live: Projekt von der Skizze zur Fertigstellung',
                'description' => 'Tischler, Maler und Bauunternehmen dokumentieren Projekte als Mini-Serie.',
                'category' => 'handwerk',
                'score' => 81,
                'valid_days' => 30,
            ],
            [
                'title' => 'Tag im Leben eines Familienbetriebs',
                'description' => 'Authentische Einblicke in den Alltag steigern Vertrauen bei lokaler Kundschaft.',
                'category' => 'dienstleistung',
                'score' => 79,
                'valid_days' => 28,
            ],
            [
                'title' => 'Saisonales Angebot der Woche',
                'description' => 'Einzelhändler bewerben wöchentlich wechselnde Aktionen mit klarem Call-to-Action.',
                'category' => 'einzelhandel',
                'score' => 74,
                'valid_days' => 7,
            ],
            [
                'title' => 'Mini-Workout für zwischendurch',
                'description' => 'Fitnessstudios und Personal Trainer teilen 30-Sekunden-Übungen für daheim.',
                'category' => 'fitness',
                'score' => 70,
                'valid_days' => 21,
            ],
            [
                'title' => 'Kundenstimmen als Reel',
                'description' => 'Echte Bewertungen werden als kurze Testimonial-Clips inszeniert.',
                'category' => 'dienstleistung',
                'score' => 68,
                'valid_days' => 30,
            ],
            [
                'title' => 'Nachhaltigkeit im Kiez zeigen',
                'description' => 'Betriebe kommunizieren regionale, plastikfreie und faire Maßnahmen.',
                'category' => 'einzelhandel',
                'score' => 65,
                'valid_days' => 30,
            ],
        ];
    }

    /**
     * @return list<array{tag: string, category: string, volume_label: string}>
     */
    private function hashtags(): array
    {
        return [
            ['tag' => '#regionalgenuss', 'category' => 'gastronomie', 'volume_label' => 'hoch'],
            ['tag' => '#ausmeinerstadt', 'category' => 'dienstleistung', 'volume_label' => 'viral'],
            ['tag' => '#unterstützelokal', 'category' => 'einzelhandel', 'volume_label' => 'hoch'],
            ['tag' => '#handgemacht', 'category' => 'handwerk', 'volume_label' => 'mittel'],
            ['tag' => '#familienbetrieb', 'category' => 'dienstleistung', 'volume_label' => 'mittel'],
            ['tag' => '#vorhernachher', 'category' => 'beauty', 'volume_label' => 'viral'],
            ['tag' => '#frischausderregion', 'category' => 'gastronomie', 'volume_label' => 'hoch'],
            ['tag' => '#kleinunternehmen', 'category' => 'einzelhandel', 'volume_label' => 'hoch'],
            ['tag' => '#meinkiez', 'category' => 'dienstleistung', 'volume_label' => 'mittel'],
            ['tag' => '#fitnessmotivation', 'category' => 'fitness', 'volume_label' => 'hoch'],
            ['tag' => '#lokalhelden', 'category' => 'dienstleistung', 'volume_label' => 'niedrig'],
            ['tag' => '#nachhaltigeinkaufen', 'category' => 'einzelhandel', 'volume_label' => 'mittel'],
        ];
    }

    /**
     * @return list<array{name: string, platform: string, external_ref: string}>
     */
    private function audio(): array
    {
        return [
            ['name' => 'Sommer-Vibes (Trending Sound)', 'platform' => 'instagram', 'external_ref' => 'instagram_audio_482910'],
            ['name' => 'Aufbau-Beat für Vorher-Nachher', 'platform' => 'tiktok', 'external_ref' => 'tiktok_audio_771203'],
            ['name' => 'Ruhiger Lo-Fi Hintergrund', 'platform' => 'instagram', 'external_ref' => 'instagram_audio_339005'],
            ['name' => 'Energiegeladener Pop-Hook', 'platform' => 'tiktok', 'external_ref' => 'tiktok_audio_905512'],
            ['name' => 'Gemütliche Café-Atmosphäre', 'platform' => 'instagram', 'external_ref' => 'instagram_audio_118874'],
            ['name' => 'Motivations-Voiceover Sound', 'platform' => 'tiktok', 'external_ref' => 'tiktok_audio_640228'],
        ];
    }

    /**
     * @return list<array{name: string, description: string, platform: string}>
     */
    private function formats(): array
    {
        return [
            ['name' => 'Vorher-Nachher', 'description' => 'Zwei-Shot-Transformation mit hartem Schnitt auf den Beat.', 'platform' => 'reels'],
            ['name' => 'Tag im Leben', 'description' => 'Chronologische Mini-Vlog-Sequenz vom Öffnen bis Schließen.', 'platform' => 'tiktok'],
            ['name' => 'Produkt-Storytelling', 'description' => 'Ein Produkt von Herkunft bis Anwendung in vier Szenen erzählt.', 'platform' => 'instagram'],
            ['name' => 'Kunden-Testimonial', 'description' => 'Echte Stimme der Kundschaft über O-Ton und Untertitel.', 'platform' => 'reels'],
            ['name' => 'Schnell-Tutorial', 'description' => 'Drei-Schritt-Anleitung in unter 15 Sekunden.', 'platform' => 'tiktok'],
            ['name' => 'Team-Vorstellung', 'description' => 'Mitarbeitende mit Name, Rolle und Lieblingsmoment.', 'platform' => 'instagram'],
            ['name' => 'Behind-the-Scenes', 'description' => 'Ungeschönte Einblicke in die tägliche Arbeit.', 'platform' => 'reels'],
        ];
    }
}
