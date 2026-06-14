<?php

namespace App\Console\Commands;

use App\Models\WebsiteAnalyzeRun;
use Illuminate\Console\Command;

class ExpireStaleWebsiteAnalyzeRunsCommand extends Command
{
    protected $signature = 'webanalyze:expire-stale';

    protected $description = 'Mark website analyze runs stuck in pending/processing beyond the timeout as failed';

    public function handle(): int
    {
        $graceSeconds = (int) config('webanalyze.timeout', 900) + 300;
        $threshold = now()->subSeconds($graceSeconds);

        $stale = WebsiteAnalyzeRun::query()
            ->whereIn('status', [
                WebsiteAnalyzeRun::STATUS_PENDING,
                WebsiteAnalyzeRun::STATUS_PROCESSING,
            ])
            ->where('created_at', '<', $threshold)
            ->where(function ($query) use ($threshold) {
                $query->whereNull('started_at')->orWhere('started_at', '<', $threshold);
            })
            ->get();

        foreach ($stale as $run) {
            $run->markFailed('Zeitüberschreitung');
        }

        $this->info("Expired {$stale->count()} stale website analyze run(s).");

        return self::SUCCESS;
    }
}
