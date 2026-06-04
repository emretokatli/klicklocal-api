<?php

namespace App\Services\Authorization;

use App\Models\User;
use App\Models\Workspace;
use App\Support\Permission;
use App\Support\PlatformRole;
use App\Support\TeamContext;
use Illuminate\Auth\Access\AuthorizationException;

class AuthorizationService
{
    public function setWorkspaceTeam(?int $workspaceId): void
    {
        setPermissionsTeamId($workspaceId);
    }

    public function clearWorkspaceTeam(): void
    {
        setPermissionsTeamId(TeamContext::PLATFORM);
    }

    public function isPlatformAdmin(User $user): bool
    {
        $this->clearWorkspaceTeam();

        return $user->hasAnyRole(PlatformRole::all());
    }

    public function isSuperAdmin(User $user): bool
    {
        $this->clearWorkspaceTeam();

        return $user->hasRole(PlatformRole::SUPER_ADMIN);
    }

    public function hasPlatformPermission(User $user, string $permission): bool
    {
        $this->clearWorkspaceTeam();

        return $user->hasPermissionTo($permission, Permission::GUARD);
    }

    public function hasWorkspacePermission(User $user, Workspace $workspace, string $permission): bool
    {
        $this->setWorkspaceTeam($workspace->id);

        if ($workspace->owner_id === $user->id) {
            return true;
        }

        return $user->hasPermissionTo($permission, Permission::GUARD);
    }

    public function assertWorkspacePermission(User $user, Workspace $workspace, string $permission): void
    {
        if (! $this->hasWorkspacePermission($user, $workspace, $permission)) {
            throw new AuthorizationException('You do not have permission to perform this action in this workspace.');
        }
    }

    public function assertPlatformPermission(User $user, string $permission): void
    {
        if (! $this->hasPlatformPermission($user, $permission)) {
            throw new AuthorizationException('Platform admin permission required.');
        }
    }

    /**
     * @return array{
     *     platform_roles: list<string>,
     *     platform_permissions: list<string>,
     *     workspace_role: string|null,
     *     workspace_permissions: list<string>
     * }
     */
    public function abilitiesForUser(User $user, ?Workspace $workspace = null): array
    {
        $this->clearWorkspaceTeam();
        $platformRoles = $user->getRoleNames()->toArray();
        $platformPermissions = $user->getAllPermissions()
            ->pluck('name')
            ->unique()
            ->values()
            ->all();

        $workspaceRole = null;
        $workspacePermissions = [];

        if ($workspace !== null) {
            $this->setWorkspaceTeam($workspace->id);
            $workspaceRole = $workspace->owner_id === $user->id
                ? 'owner'
                : ($user->getRoleNames()->first() ?: null);
            $workspacePermissions = $user->getPermissionsViaRoles()
                ->pluck('name')
                ->merge($user->getDirectPermissions()->pluck('name'))
                ->unique()
                ->values()
                ->all();
            $this->clearWorkspaceTeam();
        }

        return [
            'platform_roles' => $platformRoles,
            'platform_permissions' => $platformPermissions,
            'workspace_role' => $workspaceRole,
            'workspace_permissions' => $workspacePermissions,
        ];
    }
}
