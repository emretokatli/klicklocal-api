<?php

namespace Database\Factories;

use App\Models\TrendFormat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrendFormat>
 */
class TrendFormatFactory extends Factory
{
    protected $model = TrendFormat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Vorher-Nachher',
                'Tag im Leben',
                'Produkt-Storytelling',
                'Kunden-Testimonial',
                'Schnell-Tutorial',
                'Team-Vorstellung',
                'Behind-the-Scenes',
            ]),
            'description' => fake()->sentence(10),
            'platform' => fake()->randomElement(['instagram', 'tiktok', 'reels']),
            'source' => 'fake',
        ];
    }
}
