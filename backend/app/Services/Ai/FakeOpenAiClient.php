<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\OpenAiClientInterface;
use App\Services\Ai\DTOs\GeneratedContentDTO;
use App\Services\Ai\DTOs\GeneratedImageDTO;
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

    public function generateImage(
        string $prompt,
        array $context = [],
        string $size = '1024x1024',
        string $quality = 'standard',
    ): GeneratedImageDTO {
        $name = urlencode($context['business_name'] ?? 'Business');
        $type = urlencode($context['business_type'] ?? 'Local');

        $seed = abs(crc32($name.$type.$prompt)) % 1000;
        $imageUrl = "https://picsum.photos/seed/{$seed}/1024/1024";

        return new GeneratedImageDTO(
            imageUrl: $imageUrl,
            model: 'fake-image-model',
            revisedPrompt: $prompt,
            isFake: true,
        );
    }
}
