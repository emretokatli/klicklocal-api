<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Support\Permission;

class UserPolicy
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->hasPlatformPermission($user, Permission::MANAGE_USERS);
    }

    public function view(User $user, User $model): bool
    {
        return $this->viewAny($user);
    }

    public function updateRoles(User $user): bool
    {
        return $this->authorization->isSuperAdmin($user);
    }
}
