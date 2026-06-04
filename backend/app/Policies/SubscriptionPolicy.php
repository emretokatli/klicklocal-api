<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Support\Permission;

class SubscriptionPolicy
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->hasPlatformPermission($user, Permission::MANAGE_SUBSCRIPTIONS);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $this->viewAny($user);
    }
}
