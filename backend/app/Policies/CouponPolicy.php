<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Support\Permission;

class CouponPolicy
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

    public function update(User $user, Coupon $coupon): bool
    {
        return $this->viewAny($user);
    }
}
