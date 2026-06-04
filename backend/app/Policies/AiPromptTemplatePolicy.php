<?php

namespace App\Policies;

use App\Models\AiPromptTemplate;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Support\Permission;

class AiPromptTemplatePolicy
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->hasPlatformPermission($user, Permission::MANAGE_AI_PROMPTS);
    }

    public function view(User $user, AiPromptTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AiPromptTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, AiPromptTemplate $template): bool
    {
        return $this->authorization->hasPlatformPermission($user, Permission::MANAGE_PLATFORM_SETTINGS)
            || $this->authorization->isSuperAdmin($user);
    }
}
