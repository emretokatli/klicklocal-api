<?php

namespace App\Jobs;

use App\Contracts\Post\PostPublisherInterface;
use App\Enums\PlanFeature;
use App\Enums\PostPlatformStatus;
use App\Models\Post;
use App\Models\Workspace;
use App\Services\Billing\FeatureAccessService;
use App\Services\Media\MediaUrlService;
use App\Services\SocialProviders\Exceptions\RetryablePublishException;
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

    public function handle(
        PostPublisherInterface $publisher,
        UsageTrackingService $usageTracking,
        MediaUrlService $mediaUrl,
        FeatureAccessService $features,
    ): void {
        $post = $this->post->fresh(['platforms.socialAccount', 'media']);

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

        $workspace = Workspace::query()->find($post->workspace_id);
        $hasPlatforms = $post->platforms->isNotEmpty();

        try {
            $post->markAsProcessing();

            Log::info('Post publishing started', [
                'post_id' => $post->id,
                'workspace_id' => $post->workspace_id,
                'scheduled_at' => $post->scheduled_at?->toIso8601String(),
                'platforms' => $post->platforms->count(),
            ]);

            // Enforce the scheduled-posts quota at publish time: a post scheduled
            // earlier must not publish if the plan no longer permits it (e.g. the
            // subscription was cancelled or the feature removed after scheduling).
            if ($hasPlatforms && $workspace !== null && ! $this->publishAllowed($workspace, $features)) {
                $this->failAllPending(
                    $post,
                    'Dein aktueller Tarif erlaubt keine geplanten Veröffentlichungen mehr.',
                );
                $post->markAsFailed();

                Log::warning('PublishPostJob blocked by plan quota at publish time', [
                    'post_id' => $post->id,
                    'workspace_id' => $workspace->id,
                ]);

                return;
            }

            // Social networks download the media themselves, so verify it is
            // publicly reachable before attempting to publish.
            if (config('media.verify_public_access', true) && $post->media !== null) {
                $mediaUrl->ensurePubliclyAccessible($post->media);
            }

            $publisher->publish($post);

            if ($hasPlatforms) {
                $this->finalizeMultiPlatform($post);
            }

            if ($workspace !== null) {
                $usageTracking->recordQueueJob($workspace, self::class);
            }

            Log::info('Post publishing finished', [
                'post_id' => $post->id,
                'status' => $post->fresh()->status->value,
            ]);
        } catch (RetryablePublishException $e) {
            // One or more platforms had a transient failure; let the job retry so
            // only the still-pending platforms are re-attempted.
            Log::info('PublishPostJob will retry transient platform failures', [
                'post_id' => $post->id,
                'attempt' => $this->attempts(),
                'reason' => $e->getMessage(),
            ]);

            throw $e;
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

    /**
     * Decide the post status after a multi-platform attempt and trigger a retry
     * while transient platforms remain and attempts are left.
     */
    private function finalizeMultiPlatform(Post $post): void
    {
        $post->load('platforms');

        $pending = $post->platforms->filter(fn ($p) => $p->status === PostPlatformStatus::Pending);
        $published = $post->platforms->filter(fn ($p) => $p->status === PostPlatformStatus::Published);

        if ($pending->isNotEmpty()) {
            if ($this->attempts() < $this->tries) {
                throw RetryablePublishException::platformsPending($pending->count());
            }

            // No retries left — give up on the still-pending platforms.
            foreach ($pending as $platform) {
                $platform->markAsFailed('Maximale Veröffentlichungsversuche erreicht.');
            }
        }

        if ($published->isNotEmpty()) {
            $post->markAsPublished();

            return;
        }

        $post->markAsFailed();
    }

    private function publishAllowed(Workspace $workspace, FeatureAccessService $features): bool
    {
        $limit = $features->getFeatureLimit($workspace, PlanFeature::ScheduledPostsMonthly);

        if ($limit === null) {
            return false; // no active subscription
        }

        if (is_bool($limit)) {
            return $limit;
        }

        return $limit !== 0; // negative = unlimited, positive = has allowance
    }

    private function failAllPending(Post $post, string $reason): void
    {
        $post->load('platforms');

        foreach ($post->platforms as $platform) {
            if ($platform->status === PostPlatformStatus::Pending) {
                $platform->markAsFailed($reason);
            }
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
