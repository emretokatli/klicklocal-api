<?php

namespace Tests\Unit;

use App\Enums\PostStatus;
use App\Enums\WorkspaceRole;
use App\Jobs\PublishPostJob;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Post\PostSchedulingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PostSchedulingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function workspaceFor(User $user): Workspace
    {
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Schedule WS',
            'slug' => 'schedule-ws',
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner,
            'created_at' => now(),
        ]);

        $this->subscribeWorkspace($workspace);

        return $workspace;
    }

    public function test_schedule_dispatches_delayed_job_and_updates_post(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $workspace = $this->workspaceFor($user);
        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'title' => 'T',
            'content' => 'C',
            'status' => PostStatus::Draft,
        ]);

        $service = app(PostSchedulingService::class);
        $scheduledAt = now()->addHours(2);

        $result = $service->schedule($user, $post, [
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);

        $this->assertTrue($result->isScheduled());
        $this->assertEquals(
            $scheduledAt->toDateTimeString(),
            $result->scheduled_at->toDateTimeString(),
        );

        Queue::assertPushed(PublishPostJob::class, function (PublishPostJob $job) use ($post) {
            return $job->post->id === $post->id;
        });
    }

    public function test_schedule_rejects_past_dates(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceFor($user);
        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'status' => PostStatus::Draft,
        ]);

        $service = app(PostSchedulingService::class);

        $this->expectException(ValidationException::class);

        $service->schedule($user, $post, [
            'scheduled_at' => now()->subMinute()->toIso8601String(),
        ]);
    }

    public function test_schedule_rejects_already_scheduled_posts(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceFor($user);
        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'status' => PostStatus::Scheduled,
            'scheduled_at' => now()->addHour(),
        ]);

        $service = app(PostSchedulingService::class);

        $this->expectException(ValidationException::class);

        $service->schedule($user, $post, [
            'scheduled_at' => now()->addHours(2)->toIso8601String(),
        ]);
    }
}
