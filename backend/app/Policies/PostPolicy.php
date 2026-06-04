<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Support\Permission;

class PostPolicy
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return $this->authorization->hasWorkspacePermission(
            $user,
            $post->workspace,
            Permission::VIEW_POSTS,
        );
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Post $post): bool
    {
        return $this->authorization->hasWorkspacePermission(
            $user,
            $post->workspace,
            Permission::EDIT_POSTS,
        );
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->authorization->hasWorkspacePermission(
            $user,
            $post->workspace,
            Permission::DELETE_POSTS,
        );
    }

    public function schedule(User $user, Post $post): bool
    {
        return $this->authorization->hasWorkspacePermission(
            $user,
            $post->workspace,
            Permission::SCHEDULE_POSTS,
        ) && $post->canBeScheduled();
    }
}
