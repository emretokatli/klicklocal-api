<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\OpenAiClientInterface;
use App\Services\Ai\DTOs\GeneratedContentDTO;
use Illuminate\Support\Str;

/**
 * Deterministic local stand-in for the OpenAI API so the full onboarding flow
 * (and tests) can run without a real API key. Activated by OPENAI_DRIVER=fake.
 */
class FakeOpenAiClient implements OpenAiClientInterface
{
    public function generateContent(
        string $systemPrompt,
        string $userPrompt,
        ?string $imageUrl,
        array $context = [],
    ): GeneratedContentDTO {
        $name = $context['business_name'] ?? 'unser Geschäft';
        $type = $context['business_type'] ?? 'Geschäft';
        $city = $context['city'] ?? '';
        $topic = trim($userPrompt) !== '' ? trim($userPrompt) : ($context['products_services'] ?? 'unsere Highlights');

        $cityPart = $city !== '' ? " in {$city}" : '';

        $caption = "✨ {$name}{$cityPart}: {$topic}. "
            ."Als {$type} legen wir Wert auf Qualität und ein Erlebnis, das in Erinnerung bleibt. "
            .'Schau vorbei und überzeuge dich selbst!';

        $story = "Heute bei {$name}: {$topic} 👀 Wisch nach oben für mehr!";

        $hashtags = array_values(array_unique(array_filter([
            '#'.Str::studly(Str::slug($name, '')),
            $city !== '' ? '#'.Str::studly(Str::slug($city, '')) : null,
            '#'.Str::studly(Str::slug($type, '')),
            '#local',
            '#klicklocal',
            '#instagood',
        ])));

        return new GeneratedContentDTO(
            caption: $caption,
            storyText: $story,
            hashtags: $hashtags,
            callToAction: 'Jetzt vorbeikommen oder Termin sichern! 📲',
            model: 'fake-gpt-5',
            tokensUsed: 0,
            raw: ['fake' => true, 'image' => $imageUrl],
        );
    }
}
