<?php

namespace Tests\Unit;

use App\Contracts\Post\PostPublisherInterface;
use App\Enums\PostStatus;
use App\Jobs\PublishPostJob;
use App\Services\Media\Exceptions\MediaNotAccessibleException;
use App\Services\Media\MediaUrlService;
use App\Services\Usage\UsageTrackingService;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
            app(\App\Services\Media\MediaUrlService::class),
            app(\App\Services\Billing\FeatureAccessService::class),
        );

        $post->refresh();

        $this->assertTrue($post->isPublished());
        $this->assertNotNull($post->published_at);
    }

    public function test_job_fails_when_media_not_publicly_accessible(): void
    {
        config(['app.url' => 'https://api.example.com', 'media.verify_public_access' => true]);

        Http::fake(['*' => Http::response('', 404)]);

        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Media WS',
            'slug' => 'media-ws',
        ]);

        $media = Media::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'file_name' => 'photo.jpg',
            'file_path' => 'media/'.$workspace->id.'/photo.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
            'created_at' => now(),
        ]);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'content' => 'With media',
            'media_id' => $media->id,
            'status' => PostStatus::Scheduled,
            'scheduled_at' => now()->subMinute(),
        ]);

        $job = new PublishPostJob($post);

        $this->expectException(MediaNotAccessibleException::class);

        $job->handle(
            app(PostPublisherInterface::class),
            app(UsageTrackingService::class),
            app(MediaUrlService::class),
            app(\App\Services\Billing\FeatureAccessService::class),
        );
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
            app(\App\Services\Media\MediaUrlService::class),
            app(\App\Services\Billing\FeatureAccessService::class),
        );

        $post->refresh();

        $this->assertTrue($post->isDraft());
    }
}
