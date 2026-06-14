<?php

namespace App\Console\Commands;

use App\Jobs\ClassifyCommentsJob;
use App\Models\Comment;
use App\Models\Workspace;
use Illuminate\Console\Command;

class ClassifyCommentsCommand extends Command
{
    protected $signature = 'comments:classify
        {--workspace= : Classify a single workspace by id}
        {--limit= : Max comments per workspace run (defaults to the configured cap)}';

    protected $description = 'Dispatch AI sentiment classification jobs for unclassified comments';

    public function handle(): int
    {
        $workspaceId = $this->option('workspace');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        if ($workspaceId !== null) {
            $workspace = Workspace::query()->find((int) $workspaceId);

            if ($workspace === null) {
                $this->error("Workspace [{$workspaceId}] not found.");

                return self::FAILURE;
            }

            ClassifyCommentsJob::dispatch($workspace, null, $limit);
            $this->info("Sentiment classification dispatched for workspace [{$workspace->id}] {$workspace->name}.");

            return self::SUCCESS;
        }

        $count = 0;

        Workspace::query()
            ->whereIn('id', Comment::query()
                ->whereNull('sentiment_classified_at')
                ->select('workspace_id'))
            ->chunkById(100, function ($workspaces) use (&$count, $limit): void {
                foreach ($workspaces as $workspace) {
                    ClassifyCommentsJob::dispatch($workspace, null, $limit);
                    $count++;
                }
            });

        $this->info("Sentiment classification dispatched for {$count} workspace(s).");

        return self::SUCCESS;
    }
}
