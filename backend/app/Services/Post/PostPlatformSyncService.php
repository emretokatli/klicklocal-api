<?php

namespace App\Services\Post;

use App\Enums\PostPlatformStatus;
use App\Enums\SocialAccountStatus;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\Workspace;

class PostPlatformSyncService
{
    /**
     * @param  list<int>  $socialAccountIds
     */
    public function sync(Post $post, Workspace $workspace, array $socialAccountIds): void
    {
        if ($socialAccountIds === []) {
            $socialAccountIds = SocialAccount::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', SocialAccountStatus::Connected)
                ->pluck('id')
                ->all();
        }

        $post->platforms()->delete();

        if ($socialAccountIds === []) {
            return;
        }

        $accounts = SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('id', $socialAccountIds)
            ->get();

        foreach ($accounts as $account) {
            $post->platforms()->create([
                'social_account_id' => $account->id,
                'status' => PostPlatformStatus::Pending,
                'created_at' => now(),
            ]);
        }
    }
}
