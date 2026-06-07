<?php

namespace App\Services\Workspace;

use App\Models\User;
use App\Models\Workspace;

class OnboardingService
{
    public const TOTAL_STEPS = 4;

    public function __construct(
        private readonly WorkspaceService $workspaceService,
    ) {}

    /**
     * @param  array{step?: int|null, completed?: bool|null}  $data
     */
    public function update(User $user, int $workspaceId, array $data): Workspace
    {
        $workspace = $this->workspaceService->findForUser($user, $workspaceId);

        if (array_key_exists('step', $data) && $data['step'] !== null) {
            $workspace->onboarding_step = max(1, min(self::TOTAL_STEPS, (int) $data['step']));
        }

        if (! empty($data['completed'])) {
            $workspace->onboarding_completed_at = now();
            $workspace->onboarding_step = self::TOTAL_STEPS;
        }

        $workspace->save();

        return $workspace->fresh(['owner:id,name,email', 'businessProfile']);
    }
}
