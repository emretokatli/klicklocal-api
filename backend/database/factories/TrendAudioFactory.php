<?php

namespace Database\Factories;

use App\Models\TrendAudio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrendAudio>
 */
class TrendAudioFactory extends Factory
{
    protected $model = TrendAudio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platform = fake()->randomElement(['instagram', 'tiktok']);

        return [
            'name' => fake()->randomElement([
                'Sommer-Vibes (Trending Sound)',
                'Aufbau-Beat für Vorher-Nachher',
                'Ruhiger Lo-Fi Hintergrund',
                'Energiegeladener Pop-Hook',
                'Gemütliche Café-Atmosphäre',
                'Motivations-Voiceover Sound',
            ]),
            'platform' => $platform,
            'external_ref' => $platform.'_audio_'.fake()->unique()->numberBetween(100000, 999999),
            'source' => 'fake',
        ];
    }
}
