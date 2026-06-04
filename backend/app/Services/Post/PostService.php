<?php

namespace App\Services\Post;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Authorization\AuthorizationService;
use App\Services\Workspace\WorkspaceService;
use App\Support\Permission;
use Illuminate\Validation\ValidationException;

class PostService
{
    public function __construct(
        private readonly WorkspaceService $workspaceService,
        private readonly AuthorizationService $authorization,
    ) {}

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Post>
     */
    public function list(User $user, int $workspaceId)
    {
        $workspace = $this->workspaceService->findForUser($user, $workspaceId);

        return Post::query()
            ->where('workspace_id', $workspace->id)
            ->with(['user:id,name,email', 'media:id,file_name,file_path', 'platforms.socialAccount:id,provider,username'])
            ->latest()
            ->get();
    }

    /**
     * @param  array{title?: string, content?: string, media_id?: int|null}  $data
     */
    public function create(User $user, int $workspaceId, array $data): Post
    {
        $workspace = $this->workspaceService->findForUser($user, $workspaceId);
        $this->assertCanEdit($user, $workspace);

        $this->assertMediaInWorkspace($workspace->id, $data['media_id'] ?? null);

        return Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'title' => $data['title'] ?? null,
            'content' => $data['content'] ?? null,
            'media_id' => $data['media_id'] ?? null,
            'status' => PostStatus::Draft,
        ])->load(['user:id,name,email', 'media']);
    }

    public function find(User $user, int $postId): Post
    {
        $post = Post::with(['user:id,name,email', 'media', 'platforms.socialAccount'])
            ->findOrFail($postId);

        $this->workspaceService->findForUser($user, $post->workspace_id);

        return $post;
    }

    /**
     * @param  array{title?: string, content?: string, media_id?: int|null}  $data
     */
    public function update(User $user, Post $post, array $data): Post
    {
        $workspace = $this->workspaceService->findForUser($user, $post->workspace_id);
        $this->assertCanEdit($user, $workspace);

        if ($post->status === PostStatus::Published) {
            throw ValidationException::withMessages([
                'status' => ['Published posts cannot be edited.'],
            ]);
        }

        if (array_key_exists('media_id', $data)) {
            $this->assertMediaInWorkspace($workspace->id, $data['media_id']);
        }

        $post->update([
            'title' => $data['title'] ?? $post->title,
            'content' => $data['content'] ?? $post->content,
            'media_id' => array_key_exists('media_id', $data) ? $data['media_id'] : $post->media_id,
        ]);

        return $post->fresh(['user:id,name,email', 'platforms.socialAccount', 'media']);
    }

    private function assertMediaInWorkspace(int $workspaceId, ?int $mediaId): void
    {
        if ($mediaId === null) {
            return;
        }

        $exists = \App\Models\Media::query()
            ->where('workspace_id', $workspaceId)
            ->where('id', $mediaId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'media_id' => ['Media does not belong to this workspace.'],
            ]);
        }
    }

    public function delete(User $user, Post $post): void
    {
        $workspace = $this->workspaceService->findForUser($user, $post->workspace_id);
        $this->assertCanEdit($user, $workspace);
        $post->delete();
    }

    private function assertCanEdit(User $user, Workspace $workspace): void
    {
        if (! $this->authorization->hasWorkspacePermission($user, $workspace, Permission::EDIT_POSTS)) {
            throw ValidationException::withMessages([
                'workspace' => ['You do not have permission to manage posts in this workspace.'],
            ]);
        }
    }
}
