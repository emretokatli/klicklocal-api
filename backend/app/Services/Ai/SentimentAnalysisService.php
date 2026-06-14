<?php

namespace App\Services\Ai;

use App\Enums\UsageType;
use App\Models\Comment;
use App\Models\Workspace;
use App\Services\Ai\Contracts\OpenAiClientInterface;
use App\Services\Usage\UsageTrackingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class SentimentAnalysisService
{
    private const SENTIMENTS = ['positive', 'neutral', 'negative'];

    public function __construct(
        private readonly OpenAiClientInterface $client,
        private readonly UsageTrackingService $usage,
    ) {}

    /**
     * Classify unclassified comments of one workspace in batches. Returns the
     * number of comments classified.
     *
     * Never throws: a failed OpenAI call or unusable model output leaves the
     * affected comments unclassified (the hourly sweep retries them later), so
     * classification can never break comment ingestion.
     *
     * @param  Collection<int, Comment>  $comments
     */
    public function classifyForWorkspace(Workspace $workspace, Collection $comments): int
    {
        $pending = $comments->filter(
            fn (Comment $comment) => $comment->sentiment_classified_at === null
                && trim((string) $comment->text) !== '',
        );

        $batchSize = max(1, (int) config('comments.classification.batch_size', 20));
        $classified = 0;

        foreach ($pending->chunk($batchSize) as $batch) {
            try {
                $classified += $this->classifyBatch($workspace, $batch->values());
            } catch (Throwable $e) {
                // OpenAI down / hard error: stop sweeping, keep what we have.
                Log::warning('Sentiment classification batch failed, leaving comments unclassified', [
                    'workspace_id' => $workspace->id,
                    'comment_ids' => $batch->pluck('id')->all(),
                    'error' => $e->getMessage(),
                ]);

                break;
            }
        }

        return $classified;
    }

    /**
     * @param  Collection<int, Comment>  $batch
     */
    private function classifyBatch(Workspace $workspace, Collection $batch): int
    {
        $payload = $batch
            ->map(fn (Comment $comment) => [
                'id' => $comment->id,
                'text' => (string) $comment->text,
            ])
            ->values()
            ->all();

        $dto = $this->client->classifySentiments($this->systemPrompt(), $payload);

        if ($dto->tokensUsed > 0) {
            $this->usage->record(UsageType::Ai, 'comment_sentiment', $dto->tokensUsed, null, $workspace, [
                'unit' => 'tokens',
                'model' => $dto->model,
                'comments' => $batch->count(),
            ]);
        }

        $byId = $batch->keyBy('id');
        $classified = 0;

        foreach ($dto->results as $result) {
            // Strict validation: anything unparseable, out-of-enum, or not an
            // id from this batch is skipped — the row stays NULL for retry.
            if (! is_array($result)) {
                continue;
            }

            $id = $result['id'] ?? null;
            $sentiment = $result['sentiment'] ?? null;

            if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                continue;
            }

            if (! is_string($sentiment) || ! in_array($sentiment, self::SENTIMENTS, true)) {
                continue;
            }

            /** @var Comment|null $comment */
            $comment = $byId->get((int) $id);

            if ($comment === null || $comment->sentiment_classified_at !== null) {
                continue;
            }

            $comment->forceFill([
                'sentiment' => $sentiment,
                'sentiment_classified_at' => now(),
            ])->save();

            $classified++;
        }

        return $classified;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You classify the sentiment of social media comments for local businesses.
        The comments are mostly written in German; some may be in English or contain emojis.

        Assign each comment exactly one of these three classes:
        - "positive": praise, gratitude, enthusiasm. Beispiel: "Super Service, vielen Dank — komme gerne wieder!"
        - "neutral": questions, factual remarks, no clear emotion. Beispiel: "Habt ihr auch samstags geöffnet?"
        - "negative": complaints, disappointment, criticism. Beispiel: "Leider sehr enttäuscht, das Essen war kalt."

        You receive a JSON object {"comments": [{"id": <int>, "text": <string>}, ...]}.
        Respond ONLY with a valid JSON object of this exact shape:
        {"results": [{"id": <same id>, "sentiment": "positive" | "neutral" | "negative"}, ...]}
        Include every comment id exactly once. Do not include any text outside the JSON object.
        PROMPT;
    }
}
