<?php

namespace App\Actions\Post;

use App\Models\Post;
use App\Models\User;
use App\Services\Post\PostSchedulingService;

class SchedulePostAction
{
    public function __construct(
        private readonly PostSchedulingService $schedulingService,
    ) {}

    /**
     * @param  array{scheduled_at: string, social_account_ids?: list<int>}  $data
     */
    public function execute(User $user, Post $post, array $data): Post
    {
        return $this->schedulingService->schedule($user, $post, $data);
    }
}
