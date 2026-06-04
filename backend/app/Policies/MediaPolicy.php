<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Support\Permission;

class MediaPolicy
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Media $media): bool
    {
        return $this->authorization->hasWorkspacePermission(
            $user,
            $media->workspace,
            Permission::VIEW_MEDIA,
        );
    }

    public function upload(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->authorization->hasWorkspacePermission(
            $user,
            $media->workspace,
            Permission::EDIT_POSTS,
        );
    }
}
