<?php

namespace App\Jobs;

use App\Models\Comment;
use App\Models\Workspace;
use App\Services\Ai\SentimentAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClassifyCommentsJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * @param  list<int>|null  $commentIds  Restrict to these comments (event-driven
     *                                      path); null sweeps all unclassified
     *                                      comments of the workspace.
     * @param  int|null  $limit  Optional cap below the configured max per run.
     */
    public function __construct(
        public Workspace $workspace,
        public ?array $commentIds = null,
        public ?int $limit = null,
    ) {}

    public function handle(SentimentAnalysisService $sentiment): void
    {
        $workspace = $this->workspace->fresh();

        if ($workspace === null) {
            Log::warning('ClassifyCommentsJob skipped: workspace not found', [
                'workspace_id' => $this->workspace->id,
            ]);

            return;
        }

        // Cost guardrail: never classify more than the configured cap per run,
        // regardless of how the job was dispatched.
        $cap = max(1, (int) config('comments.classification.max_per_run', 200));
        $limit = $this->limit !== null ? min(max(1, $this->limit), $cap) : $cap;

        $query = Comment::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('sentiment_classified_at');

        if ($this->commentIds !== null) {
            $query->whereIn('id', $this->commentIds);
        }

        $comments = $query->orderBy('id')->limit($limit)->get();

        if ($comments->isEmpty()) {
            return;
        }

        $classified = $sentiment->classifyForWorkspace($workspace, $comments);

        Log::info('Comment sentiment classification finished', [
            'workspace_id' => $workspace->id,
            'candidates' => $comments->count(),
            'classified' => $classified,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ClassifyCommentsJob failed permanently', [
            'workspace_id' => $this->workspace->id,
            'comment_ids' => $this->commentIds,
            'error' => $exception?->getMessage(),
        ]);
    }
}
