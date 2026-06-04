<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Support\Permission;

class PlanPolicy
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->hasPlatformPermission($user, Permission::MANAGE_PLANS);
    }

    public function view(User $user, Plan $plan): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Plan $plan): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $this->authorization->isSuperAdmin($user);
    }
}
