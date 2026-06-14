<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\OpenAiClientInterface;
use App\Services\Ai\DTOs\GeneratedContentDTO;
use App\Services\Ai\DTOs\GeneratedImageDTO;
use App\Services\Ai\DTOs\SentimentBatchDTO;
use App\Services\Ai\DTOs\SuggestedReplyDTO;
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

    public function classifySentiments(string $systemPrompt, array $comments): SentimentBatchDTO
    {
        $results = [];

        foreach ($comments as $comment) {
            $results[] = [
                'id' => $comment['id'],
                'sentiment' => $this->keywordSentiment($comment['text']),
            ];
        }

        return new SentimentBatchDTO(
            results: $results,
            model: 'fake-sentiment-model',
            tokensUsed: 0,
        );
    }

    public function suggestCommentReply(
        string $systemPrompt,
        string $userPrompt,
        array $context = [],
    ): SuggestedReplyDTO {
        $name = trim($context['business_name'] ?? '') !== '' ? $context['business_name'] : 'unser Team';
        $commentText = $context['comment_text'] ?? $userPrompt;

        $reply = match ($this->keywordSentiment($commentText)) {
            'positive' => "Vielen Dank für dein tolles Feedback! Das freut uns bei {$name} riesig. 😊",
            'negative' => "Das tut uns leid! Melde dich gerne direkt bei uns — wir von {$name} finden bestimmt eine Lösung.",
            default => "Danke für deine Nachricht! Wir von {$name} melden uns gerne mit allen Details — schreib uns einfach eine DM. 😊",
        };

        return new SuggestedReplyDTO(
            replyText: $reply,
            model: 'fake-reply-model',
            tokensUsed: 0,
        );
    }

    public function commentOnTrends(string $systemPrompt, array $trends, array $context = []): array
    {
        $type = trim($context['business_type'] ?? '') !== '' ? $context['business_type'] : 'lokalen Betrieb';

        $comments = [];

        foreach ($trends as $id => $trend) {
            $title = trim((string) ($trend['title'] ?? 'dieser Trend'));
            $fit = ! empty($trend['fit']);

            $comments[(int) $id] = $fit
                ? "Dieser Trend passt gut zu deinem {$type}: „{$title}“ lässt sich leicht als kurzes "
                    .'Reel oder als Story umsetzen und bringt lokale Reichweite.'
                : "„{$title}“ ist aktuell angesagt, passt aber nur bedingt zu deinem {$type} — "
                    .'eher als Inspiration zu sehen.';
        }

        return $comments;
    }

    /**
     * Deterministic keyword heuristic so local dev and tests get stable,
     * meaningful sentiments. Negative wins over positive on mixed text.
     */
    private function keywordSentiment(string $text): string
    {
        $haystack = mb_strtolower($text);

        $negative = ['schlecht', 'enttäuscht', 'enttäuschend', 'nie wieder', 'unfreundlich', 'schade', 'frech', 'kalt'];
        $positive = ['super', 'danke', 'toll', 'klasse', 'lecker', 'empfehlen', 'großartig', 'liebe', '❤'];

        foreach ($negative as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return 'negative';
            }
        }

        foreach ($positive as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return 'positive';
            }
        }

        return 'neutral';
    }
}
