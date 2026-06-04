<?php

namespace App\Actions\Post;

use App\Jobs\PublishPostJob;
use App\Models\Post;
use App\Models\User;
use App\Services\Post\PostPlatformSyncService;
use App\Services\Workspace\WorkspaceService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PublishPostNowAction
{
    public function __construct(
        private readonly WorkspaceService $workspaceService,
        private readonly PostPlatformSyncService $platformSync,
    ) {}

    /**
     * @param  array{social_account_ids?: list<int>}  $data
     */
    public function execute(User $user, Post $post, array $data = []): Post
    {
        if (! $post->canBeScheduled()) {
            throw ValidationException::withMessages([
                'status' => ['Only draft or failed posts can be published.'],
            ]);
        }

        $workspace = $this->workspaceService->findForUser($user, $post->workspace_id);

        $post->markAsScheduled(Carbon::now());

        $this->platformSync->sync($post, $workspace, $data['social_account_ids'] ?? []);

        PublishPostJob::dispatch($post);

        return $post->fresh(['user:id,name,email', 'platforms.socialAccount', 'media']);
    }
}
