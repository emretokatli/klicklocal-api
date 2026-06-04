<?php

namespace App\Jobs;

use App\Contracts\Post\PostPublisherInterface;
use App\Models\Post;
use App\Models\Workspace;
use App\Services\Usage\UsageTrackingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishPostJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public Post $post,
    ) {}

    public function handle(PostPublisherInterface $publisher, UsageTrackingService $usageTracking): void
    {
        $post = $this->post->fresh(['platforms.socialAccount']);

        if ($post === null) {
            Log::warning('PublishPostJob skipped: post not found', [
                'post_id' => $this->post->id,
            ]);

            return;
        }

        if (! $post->isScheduled()) {
            Log::info('PublishPostJob skipped: post is not scheduled', [
                'post_id' => $post->id,
                'status' => $post->status->value,
            ]);

            return;
        }

        try {
            $post->markAsProcessing();

            Log::info('Post publishing started', [
                'post_id' => $post->id,
                'workspace_id' => $post->workspace_id,
                'scheduled_at' => $post->scheduled_at?->toIso8601String(),
            ]);

            $publisher->publish($post);

            $workspace = Workspace::query()->find($post->workspace_id);
            if ($workspace !== null) {
                $usageTracking->recordQueueJob($workspace, self::class);
            }

            Log::info('Post publishing finished', [
                'post_id' => $post->id,
                'status' => $post->fresh()->status->value,
            ]);
        } catch (Throwable $e) {
            Log::error('Post publishing failed in job', [
                'post_id' => $post->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $post->markAsFailed();
            }

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $post = Post::query()->find($this->post->id);

        if ($post !== null && ! $post->isPublished()) {
            $post->markAsFailed();
        }

        Log::error('PublishPostJob failed permanently', [
            'post_id' => $this->post->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
