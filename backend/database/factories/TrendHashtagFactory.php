<?php

namespace Database\Factories;

use App\Models\TrendHashtag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrendHashtag>
 */
class TrendHashtagFactory extends Factory
{
    protected $model = TrendHashtag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['gastronomie', 'handwerk', 'einzelhandel', 'beauty', 'fitness', 'dienstleistung'];

        return [
            'tag' => '#'.fake()->unique()->randomElement([
                'regionalgenuss', 'handgemacht', 'ausmeinerstadt', 'kleinunternehmen',
                'lokalhelden', 'frischausderregion', 'meinkiez', 'unterstützelokal',
                'madeingermany', 'familienbetrieb', 'nachhaltigeinkaufen', 'stadtleben',
            ]),
            'category' => fake()->randomElement($categories),
            'volume_label' => fake()->randomElement(['niedrig', 'mittel', 'hoch', 'viral']),
            'source' => 'fake',
        ];
    }
}
