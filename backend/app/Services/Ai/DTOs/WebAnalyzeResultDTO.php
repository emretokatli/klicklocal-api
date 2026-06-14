<?php

namespace App\Services\Ai\DTOs;

readonly class WebAnalyzeResultDTO
{
    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public string $website,
        public string $reportMarkdown,
        public ?int $score,
        public ?string $band,
        public ?string $sessionId,
        public ?int $durationMs,
        public ?string $model,
        public array $errors = [],
        public ?float $totalCostUsd = null,
        public ?int $numTurns = null,
        public bool $cached = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'website' => $this->website,
            'score' => $this->score,
            'band' => $this->band,
            'report_markdown' => $this->reportMarkdown,
            'session_id' => $this->sessionId,
            'duration_ms' => $this->durationMs,
            'model' => $this->model,
            'errors' => $this->errors,
            'total_cost_usd' => $this->totalCostUsd,
            'num_turns' => $this->numTurns,
            'cached' => $this->cached,
        ];
    }
}
