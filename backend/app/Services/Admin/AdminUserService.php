<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Support\Permission;
use App\Support\PlatformRole;
use App\Support\TeamContext;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class AdminUserService
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    /**
     * @return Collection<int, User>
     */
    public function list(): Collection
    {
        return User::query()
            ->latest()
            ->get()
            ->each(fn (User $user) => $this->attachPlatformRoles($user));
    }

    public function find(int $id): User
    {
        $user = User::query()->findOrFail($id);
        $this->attachPlatformRoles($user);

        return $user;
    }

    /**
     * @param  list<string>  $roles
     */
    public function syncPlatformRoles(User $user, array $roles): User
    {
        $this->authorization->clearWorkspaceTeam();

        $valid = array_values(array_intersect($roles, PlatformRole::all()));
        $roleModels = Role::query()
            ->whereIn('name', $valid)
            ->where('guard_name', Permission::GUARD)
            ->where('workspace_id', TeamContext::PLATFORM)
            ->get();
        $user->syncRoles($roleModels);

        return $this->find($user->id);
    }

    private function attachPlatformRoles(User $user): void
    {
        $this->authorization->clearWorkspaceTeam();
        $user->setAttribute(
            'platform_roles',
            $user->getRoleNames()->values()->all(),
        );
    }
}
