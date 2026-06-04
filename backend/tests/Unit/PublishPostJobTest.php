<?php

namespace Tests\Unit;

use App\Contracts\Post\PostPublisherInterface;
use App\Enums\PostStatus;
use App\Jobs\PublishPostJob;
use App\Services\Usage\UsageTrackingService;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishPostJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_marks_post_published_when_simulating_without_platforms(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Pub WS',
            'slug' => 'pub-ws',
        ]);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'title' => 'Hello',
            'content' => 'World',
            'status' => PostStatus::Scheduled,
            'scheduled_at' => now()->subMinute(),
        ]);

        $job = new PublishPostJob($post);
        $job->handle(
            app(PostPublisherInterface::class),
            app(UsageTrackingService::class),
        );

        $post->refresh();

        $this->assertTrue($post->isPublished());
        $this->assertNotNull($post->published_at);
    }

    public function test_job_skips_when_post_is_not_scheduled(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Skip WS',
            'slug' => 'skip-ws',
        ]);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'status' => PostStatus::Draft,
        ]);

        $job = new PublishPostJob($post);
        $job->handle(
            app(PostPublisherInterface::class),
            app(UsageTrackingService::class),
        );

        $post->refresh();

        $this->assertTrue($post->isDraft());
    }
}
