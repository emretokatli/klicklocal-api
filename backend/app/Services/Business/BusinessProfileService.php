<?php

namespace App\Services\Business;

use App\Models\BusinessProfile;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Authorization\AuthorizationService;
use App\Services\Workspace\WorkspaceService;
use App\Support\Permission;
use Illuminate\Validation\ValidationException;

class BusinessProfileService
{
    public function __construct(
        private readonly WorkspaceService $workspaceService,
        private readonly AuthorizationService $authorization,
    ) {}

    public function show(User $user, int $workspaceId): ?BusinessProfile
    {
        $workspace = $this->workspaceService->findForUser($user, $workspaceId);

        return $workspace->businessProfile;
    }

    /**
     * @param  array{
     *     business_name?: string,
     *     business_type?: string|null,
     *     city?: string|null,
     *     description?: string|null,
     *     tone_of_voice?: string|null,
     *     products_services?: string|null
     * }  $data
     */
    public function upsert(User $user, int $workspaceId, array $data): BusinessProfile
    {
        $workspace = $this->workspaceService->findForUser($user, $workspaceId);
        $this->assertCanManage($user, $workspace);

        $profile = $workspace->businessProfile()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            $data,
        );

        return $profile->fresh();
    }

    private function assertCanManage(User $user, Workspace $workspace): void
    {
        if (! $this->authorization->hasWorkspacePermission($user, $workspace, Permission::MANAGE_WORKSPACE)) {
            throw ValidationException::withMessages([
                'workspace' => ['You do not have permission to manage this business profile.'],
            ]);
        }
    }
}
