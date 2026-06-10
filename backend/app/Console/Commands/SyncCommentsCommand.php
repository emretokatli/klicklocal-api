<?php

namespace App\Console\Commands;

use App\Enums\SocialAccountStatus;
use App\Jobs\SyncCommentsJob;
use App\Models\Workspace;
use Illuminate\Console\Command;

class SyncCommentsCommand extends Command
{
    protected $signature = 'comments:sync {--workspace= : Sync a single workspace by id}';

    protected $description = 'Dispatch comment sync jobs for workspaces with connected social accounts';

    public function handle(): int
    {
        $workspaceId = $this->option('workspace');

        if ($workspaceId !== null) {
            $workspace = Workspace::query()->find((int) $workspaceId);

            if ($workspace === null) {
                $this->error("Workspace [{$workspaceId}] not found.");

                return self::FAILURE;
            }

            SyncCommentsJob::dispatch($workspace);
            $this->info("Comment sync dispatched for workspace [{$workspace->id}] {$workspace->name}.");

            return self::SUCCESS;
        }

        $count = 0;

        Workspace::query()
            ->whereHas('socialAccounts', function ($query): void {
                $query->where('status', SocialAccountStatus::Connected);
            })
            ->chunkById(100, function ($workspaces) use (&$count): void {
                foreach ($workspaces as $workspace) {
                    SyncCommentsJob::dispatch($workspace);
                    $count++;
                }
            });

        $this->info("Comment sync dispatched for {$count} workspace(s).");

        return self::SUCCESS;
    }
}
