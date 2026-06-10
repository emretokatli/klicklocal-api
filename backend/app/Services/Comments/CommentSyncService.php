<?php

namespace App\Services\Comments;

use App\Enums\PostPlatformStatus;
use App\Enums\SocialAccountStatus;
use App\Events\CommentsIngested;
use App\Models\Comment;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\SocialProviders\Contracts\FetchesComments;
use App\Services\SocialProviders\Factory\SocialProviderFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

class CommentSyncService
{
    public function __construct(
        private readonly SocialProviderFactory $providerFactory,
    ) {}

    /**
     * Pull comments from every connected, comment-capable social account of the
     * workspace into the `comments` table. Returns the number of new comments.
     *
     * Per-account failures (expired token, missing permission, API errors) are
     * logged and skipped — this method never throws for provider errors.
     */
    public function syncWorkspace(Workspace $workspace): int
    {
        $accounts = $workspace->socialAccounts()
            ->where('status', SocialAccountStatus::Connected)
            ->get();

        $newCommentIds = [];

        foreach ($accounts as $account) {
            try {
                $provider = $this->providerFactory->make($account->provider, $account);
            } catch (Throwable $e) {
                Log::warning('Comment sync: could not resolve provider', [
                    'workspace_id' => $workspace->id,
                    'social_account_id' => $account->id,
                    'provider' => $account->provider,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if (! $provider instanceof FetchesComments || ! $provider->supports('fetch_comments')) {
                Log::info('Comment sync: provider lacks fetch_comments capability, skipping', [
                    'workspace_id' => $workspace->id,
                    'social_account_id' => $account->id,
                    'provider' => $account->provider,
                ]);

                continue;
            }

            try {
                $newCommentIds = array_merge(
                    $newCommentIds,
                    $this->syncAccount($workspace, $account, $provider),
                );
            } catch (Throwable $e) {
                // Token expired / permission revoked / API down: skip the
                // account, keep syncing the rest of the workspace.
                Log::warning('Comment sync: account skipped after provider error', [
                    'workspace_id' => $workspace->id,
                    'social_account_id' => $account->id,
                    'provider' => $account->provider,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($newCommentIds !== []) {
            CommentsIngested::dispatch($workspace, $newCommentIds);
        }

        return count($newCommentIds);
    }

    /**
     * @return list<int> ids of newly created comments
     */
    private function syncAccount(
        Workspace $workspace,
        SocialAccount $account,
        FetchesComments $provider,
    ): array {
        $platforms = PostPlatform::query()
            ->where('social_account_id', $account->id)
            ->where('status', PostPlatformStatus::Published)
            ->whereNotNull('platform_post_id')
            ->whereHas('post', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->with('post')
            ->get();

        $newCommentIds = [];

        foreach ($platforms as $platform) {
            $since = Comment::query()
                ->where('platform', $account->provider)
                ->where('post_id', $platform->post_id)
                ->max('commented_at');

            $collection = $provider->fetchComments(
                $account,
                (string) $platform->platform_post_id,
                $since !== null ? (string) $since : null,
            );

            foreach ($collection->comments as $dto) {
                $comment = Comment::query()->firstOrCreate(
                    [
                        'platform' => $account->provider,
                        'external_id' => $dto->externalId,
                    ],
                    [
                        'workspace_id' => $workspace->id,
                        'post_id' => $platform->post_id,
                        'author' => $dto->author,
                        'text' => $dto->text,
                        'commented_at' => $dto->commentedAt ?? now(),
                        // `sentiment` stays at its DB default ('neutral');
                        // classification happens via the CommentsIngested event.
                    ],
                );

                if ($comment->wasRecentlyCreated) {
                    $newCommentIds[] = $comment->id;
                }
            }
        }

        if ($newCommentIds !== []) {
            Log::info('Comment sync: stored new comments', [
                'workspace_id' => $workspace->id,
                'social_account_id' => $account->id,
                'provider' => $account->provider,
                'count' => count($newCommentIds),
            ]);
        }

        return $newCommentIds;
    }
}
