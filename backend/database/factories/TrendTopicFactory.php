<?php

namespace Database\Factories;

use App\Models\TrendTopic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrendTopic>
 */
class TrendTopicFactory extends Factory
{
    protected $model = TrendTopic::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['gastronomie', 'handwerk', 'einzelhandel', 'beauty', 'fitness', 'dienstleistung'];

        $titles = [
            'Regionale Zutaten im Rampenlicht',
            'Behind-the-Scenes aus dem Betrieb',
            'Kundenstimmen als Reel',
            'Saisonales Angebot der Woche',
            'Team-Vorstellung im Kurzvideo',
            'Vorher-Nachher aus dem Salon',
            'Tag-im-Leben eines lokalen Betriebs',
            'Nachhaltigkeit im Kiez zeigen',
        ];

        $validFrom = fake()->dateTimeBetween('-1 week', 'now');

        return [
            'title' => fake()->randomElement($titles),
            'description' => fake()->sentence(12),
            'category' => fake()->randomElement($categories),
            'score' => fake()->numberBetween(40, 100),
            'source' => 'fake',
            'valid_from' => $validFrom,
            'valid_until' => fake()->dateTimeBetween($validFrom, '+3 weeks'),
            'raw_payload' => [
                'simulated' => true,
                'region' => 'DE',
            ],
        ];
    }
}
