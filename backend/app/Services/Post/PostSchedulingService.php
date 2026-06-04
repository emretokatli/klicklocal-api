<?php

namespace App\Services\Post;

use App\Jobs\PublishPostJob;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Actions\Billing\IncrementFeatureUsageAction;
use App\Enums\PlanFeature;
use App\Services\Billing\FeatureAccessService;
use App\Services\Workspace\WorkspaceService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PostSchedulingService
{
    public function __construct(
        private readonly WorkspaceService $workspaceService,
        private readonly FeatureAccessService $features,
        private readonly IncrementFeatureUsageAction $incrementUsage,
        private readonly PostPlatformSyncService $platformSync,
    ) {}

    /**
     * @param  array{scheduled_at: string, social_account_ids?: list<int>}  $data
     */
    public function schedule(User $user, Post $post, array $data): Post
    {
        $workspace = $this->workspaceService->findForUser($user, $post->workspace_id);
        $this->assertCanEdit($user, $workspace);

        if (! $post->canBeScheduled()) {
            throw ValidationException::withMessages([
                'status' => ['Only draft or failed posts can be scheduled.'],
            ]);
        }

        $this->features->assertCanUseFeature($workspace, PlanFeature::ScheduledPostsMonthly);

        $scheduledAt = $this->validateScheduleDate($data['scheduled_at']);

        $post->markAsScheduled($scheduledAt);

        $this->incrementUsage->execute($workspace, PlanFeature::ScheduledPostsMonthly);

        $this->platformSync->sync($post, $workspace, $data['social_account_ids'] ?? []);

        PublishPostJob::dispatch($post)->delay($scheduledAt);

        return $post->fresh(['user:id,name,email', 'platforms.socialAccount', 'media']);
    }

    public function validateScheduleDate(string $scheduledAt): Carbon
    {
        $date = Carbon::parse($scheduledAt);

        if ($date->isPast()) {
            throw ValidationException::withMessages([
                'scheduled_at' => ['Scheduled time must be in the future.'],
            ]);
        }

        return $date;
    }

    private function assertCanEdit(User $user, Workspace $workspace): void
    {
        $membership = $this->workspaceService->membership($user, $workspace);

        if ($membership === null || ! $membership->role->canEditContent()) {
            throw ValidationException::withMessages([
                'workspace' => ['You do not have permission to manage posts in this workspace.'],
            ]);
        }
    }
}
