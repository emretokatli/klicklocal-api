<?php

namespace App\Jobs;

use App\Models\Workspace;
use App\Services\Comments\CommentSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncCommentsJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public Workspace $workspace,
    ) {}

    public function handle(CommentSyncService $commentSync): void
    {
        $workspace = $this->workspace->fresh();

        if ($workspace === null) {
            Log::warning('SyncCommentsJob skipped: workspace not found', [
                'workspace_id' => $this->workspace->id,
            ]);

            return;
        }

        $created = $commentSync->syncWorkspace($workspace);

        Log::info('Comment sync finished', [
            'workspace_id' => $workspace->id,
            'new_comments' => $created,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SyncCommentsJob failed permanently', [
            'workspace_id' => $this->workspace->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
