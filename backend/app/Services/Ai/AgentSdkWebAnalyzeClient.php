<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\WebAnalyzeClientInterface;
use App\Services\Ai\DTOs\WebAnalyzeResultDTO;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AgentSdkWebAnalyzeClient implements WebAnalyzeClientInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $nodeBinary,
        private readonly string $scriptPath,
        private readonly string $projectRoot,
        private readonly int $timeout,
        private readonly int $maxTurns,
        private readonly float $maxBudgetUsd,
        private readonly ?string $model,
    ) {}

    public function analyze(string $website): WebAnalyzeResultDTO
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        if (! is_file($this->scriptPath)) {
            throw new RuntimeException("Agent SDK script not found at {$this->scriptPath}");
        }

        $url = $this->normalizeUrl($website);

        try {
            $result = Process::timeout($this->timeout)
                ->path($this->projectRoot)
                ->env($this->subprocessEnvironment())
                ->run([
                    $this->nodeBinary,
                    $this->scriptPath,
                    $url,
                ]);
        } catch (ProcessTimedOutException) {
            throw ValidationException::withMessages([
                'website' => [
                    "Analysis timed out after {$this->timeout} seconds. "
                    .'Check that a queue worker is running and consider lowering WEBANALYZE_MAX_TURNS.',
                ],
            ]);
        }

        $stdout = trim($result->output());
        $payload = json_decode($stdout, true);

        if (! is_array($payload)) {
            $stderr = trim($result->errorOutput());

            throw ValidationException::withMessages([
                'website' => [
                    'Website analysis failed: invalid Agent SDK response.'
                    .($stderr !== '' ? " {$stderr}" : ''),
                ],
            ]);
        }

        return $this->buildResultFromPayload($url, $payload, $result->exitCode());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildResultFromPayload(string $url, array $payload, ?int $exitCode): WebAnalyzeResultDTO
    {
        $reportMarkdown = trim((string) ($payload['report_markdown'] ?? ''));
        $errors = array_values(array_filter(
            (array) ($payload['errors'] ?? []),
            fn ($error) => is_string($error) && $error !== '',
        ));

        $dto = new WebAnalyzeResultDTO(
            website: $url,
            reportMarkdown: $reportMarkdown,
            score: null,
            band: null,
            sessionId: filled($payload['session_id'] ?? null) ? (string) $payload['session_id'] : null,
            durationMs: isset($payload['duration_ms']) ? (int) $payload['duration_ms'] : null,
            model: filled($payload['model'] ?? null) ? (string) $payload['model'] : null,
            errors: $errors,
            totalCostUsd: isset($payload['total_cost_usd']) ? (float) $payload['total_cost_usd'] : null,
            numTurns: isset($payload['num_turns']) ? (int) $payload['num_turns'] : null,
        );

        if ($reportMarkdown !== '') {
            [$score, $band] = WebAnalyzeReportParser::parseScore($reportMarkdown);
            $dto = new WebAnalyzeResultDTO(
                website: $dto->website,
                reportMarkdown: $dto->reportMarkdown,
                score: $score,
                band: $band,
                sessionId: $dto->sessionId,
                durationMs: $dto->durationMs,
                model: $dto->model,
                errors: $dto->errors,
                totalCostUsd: $dto->totalCostUsd,
                numTurns: $dto->numTurns,
            );
        }

        if ($payload['success'] ?? false) {
            if ($reportMarkdown === '') {
                throw ValidationException::withMessages([
                    'website' => ['Website analysis returned an empty report.'],
                ]);
            }

            return $dto;
        }

        $message = $errors[0] ?? match ($exitCode) {
            3 => 'Analysis ended early. A partial report may be available.',
            default => 'Website analysis failed. Please try again.',
        };

        if ($reportMarkdown !== '') {
            throw new WebAnalyzePartialResultException($message, $dto);
        }

        throw ValidationException::withMessages([
            'website' => [$message],
        ]);
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://'.$url;
        }

        return $url;
    }

    /**
     * @return array<string, string>
     */
    private function subprocessEnvironment(): array
    {
        $base = array_merge($_ENV, $_SERVER);
        $env = [];

        foreach ($base as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $env[$key] = $value;
            }
        }

        $env['ANTHROPIC_API_KEY'] = $this->apiKey;
        $env['WEBANALYZE_MAX_TURNS'] = (string) $this->maxTurns;
        $env['WEBANALYZE_MAX_BUDGET_USD'] = (string) $this->maxBudgetUsd;

        if ($this->model !== null && $this->model !== '') {
            $env['WEBANALYZE_MODEL'] = $this->model;
        }

        return $env;
    }
}
