<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Authorization\AuthorizationService;
use App\Services\Workspace\WorkspaceService;
use App\Support\Permission;

class WorkspacePolicy
{
    public function __construct(
        private readonly WorkspaceService $workspaceService,
        private readonly AuthorizationService $authorization,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $this->authorization->hasWorkspacePermission($user, $workspace, Permission::VIEW_WORKSPACE);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $this->authorization->hasWorkspacePermission($user, $workspace, Permission::MANAGE_WORKSPACE);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $this->authorization->hasWorkspacePermission($user, $workspace, Permission::MANAGE_MEMBERS);
    }
}
