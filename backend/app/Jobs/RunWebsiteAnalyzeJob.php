<?php

namespace App\Jobs;

use App\Models\WebsiteAnalyzeRun;
use App\Services\Ai\WebAnalyzePartialResultException;
use App\Services\Ai\WebAnalyzeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class RunWebsiteAnalyzeJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public int $tries = 1;

    public function __construct(
        public WebsiteAnalyzeRun $run,
    ) {
        $this->timeout = max(120, (int) config('webanalyze.timeout', 900)) + 60;
    }

    public function handle(WebAnalyzeService $analyzer): void
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $run = $this->run->fresh();

        if ($run === null || $run->isFinished()) {
            return;
        }

        $run->update([
            'status' => WebsiteAnalyzeRun::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        try {
            $result = $analyzer->analyze($run->website);

            $run->markCompleted(
                result: $result->toArray(),
                totalCostUsd: $result->totalCostUsd,
                numTurns: $result->numTurns,
            );
        } catch (WebAnalyzePartialResultException $exception) {
            $result = $exception->result;

            $run->markFailed(
                message: $exception->getMessage(),
                result: $result->toArray(),
                partial: true,
                totalCostUsd: $result->totalCostUsd,
                numTurns: $result->numTurns,
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? 'Website analysis failed.';

            $run->markFailed(is_string($message) ? $message : 'Website analysis failed.');
        } catch (Throwable $exception) {
            Log::error('RunWebsiteAnalyzeJob failed', [
                'run_id' => $run->id,
                'website' => $run->website,
                'message' => $exception->getMessage(),
            ]);

            $run->markFailed($exception->getMessage());
        }
    }

    /**
     * Safety net when the job dies outside handle() (worker crash, timeout
     * kill): without this the run would stay "processing" forever.
     */
    public function failed(?Throwable $exception): void
    {
        $run = $this->run->fresh();

        if ($run === null || $run->isFinished()) {
            return;
        }

        $run->markFailed($exception?->getMessage() ?? 'Website analysis job failed.');
    }
}
