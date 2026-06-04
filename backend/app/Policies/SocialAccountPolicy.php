<?php

namespace App\Policies;

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Authorization\AuthorizationService;
use App\Support\Permission;

class SocialAccountPolicy
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $this->authorization->hasWorkspacePermission(
            $user,
            $workspace,
            Permission::MANAGE_SOCIAL_ACCOUNTS,
        );
    }

    public function connect(User $user, Workspace $workspace): bool
    {
        return $this->authorization->hasWorkspacePermission(
            $user,
            $workspace,
            Permission::MANAGE_SOCIAL_ACCOUNTS,
        );
    }

    public function disconnect(User $user, SocialAccount $account): bool
    {
        return $this->authorization->hasWorkspacePermission(
            $user,
            $account->workspace,
            Permission::MANAGE_SOCIAL_ACCOUNTS,
        );
    }

    public function viewStatus(User $user, Workspace $workspace): bool
    {
        return $this->authorization->hasWorkspacePermission(
            $user,
            $workspace,
            Permission::MANAGE_SOCIAL_ACCOUNTS,
        );
    }
}
