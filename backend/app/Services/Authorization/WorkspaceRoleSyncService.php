<?php

namespace App\Services\Authorization;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\Permission;
use App\Support\WorkspaceRoleName;
use Spatie\Permission\Models\Role;

class WorkspaceRoleSyncService
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function assignOwner(User $user, Workspace $workspace): void
    {
        $this->assignRole($user, $workspace, WorkspaceRoleName::OWNER);
    }

    public function assignMember(User $user, Workspace $workspace, WorkspaceRole $role): void
    {
        $this->assignRole($user, $workspace, $role->spatieName());
    }

    public function syncFromMembership(WorkspaceMember $membership): void
    {
        $this->assignRole(
            $membership->user,
            $membership->workspace,
            $membership->role->spatieName(),
        );
    }

    public function removeFromWorkspace(User $user, Workspace $workspace): void
    {
        $this->authorization->setWorkspaceTeam($workspace->id);
        $user->syncRoles([]);
        $this->authorization->clearWorkspaceTeam();
    }

    private function assignRole(User $user, Workspace $workspace, string $roleName): void
    {
        $this->authorization->clearWorkspaceTeam();
        $role = Role::findByName($roleName, Permission::GUARD);

        $this->authorization->setWorkspaceTeam($workspace->id);
        $user->syncRoles([$role]);
        $this->authorization->clearWorkspaceTeam();
    }
}
