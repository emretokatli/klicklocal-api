<?php

namespace App\Services\SocialProviders\Fake;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\SocialProviders\Base\BaseSocialProvider;
use App\Services\SocialProviders\Contracts\AnalyzesContent;
use App\Services\SocialProviders\Contracts\FetchesComments;
use App\Services\SocialProviders\DTOs\CommentCollectionDTO;
use App\Services\SocialProviders\DTOs\CommentDTO;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;
use App\Services\SocialProviders\Fake\Concerns\SimulatesApiPublishing;
use App\Services\SocialProviders\Fake\Concerns\SimulatesContentAnalysis;
use Illuminate\Support\Carbon;

class FakeInstagramProvider extends BaseSocialProvider implements FetchesComments, AnalyzesContent
{
    use SimulatesApiPublishing;
    use SimulatesContentAnalysis;

    /**
     * Demo comment pool: mixed positive / neutral / negative German wording.
     *
     * @var list<array{author: string, text: string}>
     */
    private const DEMO_COMMENTS = [
        ['author' => 'anna.m_koeln', 'text' => 'Sieht richtig gut aus, da komme ich am Wochenende vorbei! 😍'],
        ['author' => 'der_lukas89', 'text' => 'Wie sind eure Öffnungszeiten am Sonntag?'],
        ['author' => 'sabine.fitness', 'text' => 'Tolle Idee, weiter so! 👏'],
        ['author' => 'maxmustermann_', 'text' => 'War letzte Woche da — leider ziemlich lange Wartezeit.'],
        ['author' => 'julia.unterwegs', 'text' => 'Gibt es das auch vegan?'],
        ['author' => 'thomas.b_1990', 'text' => 'Preise sind zuletzt ganz schön gestiegen, schade.'],
        ['author' => 'lena_liebt_essen', 'text' => 'Bestes Team der Stadt, immer wieder gerne! ❤️'],
        ['author' => 'frank.meier.official', 'text' => 'Habt ihr noch Termine diese Woche frei?'],
    ];

    public function platform(): string
    {
        return 'instagram';
    }

    /**
     * Deterministic fake comments: ids derive from the media id, so repeated
     * syncs always produce the same external_ids and dedupe cleanly.
     */
    public function fetchComments(
        SocialAccount $account,
        string $providerMediaId,
        ?string $since = null,
    ): CommentCollectionDTO {
        $this->ensureCapability('fetch_comments');
        $this->ensureValidAccount();

        $seed = crc32($providerMediaId);
        $count = 2 + ($seed % 3); // 2-4 comments per media

        $comments = [];

        for ($i = 0; $i < $count; $i++) {
            $demo = self::DEMO_COMMENTS[($seed + $i) % count(self::DEMO_COMMENTS)];
            $externalId = "{$providerMediaId}_comment_{$i}";
            $commentedAt = Carbon::createFromTimestamp(
                now()->startOfDay()->getTimestamp() - (($seed + $i * 7) % 86400),
            );

            if ($since !== null && $commentedAt->lessThanOrEqualTo(Carbon::parse($since))) {
                continue;
            }

            $comments[] = new CommentDTO(
                externalId: $externalId,
                author: $demo['author'],
                text: $demo['text'],
                commentedAt: $commentedAt,
                raw: ['simulated' => true, 'media_id' => $providerMediaId],
            );
        }

        $this->logInfo('Fetched Instagram comments (simulated)', [
            'provider_media_id' => $providerMediaId,
            'count' => count($comments),
        ]);

        return new CommentCollectionDTO($comments);
    }

    public function publish(Post $post): PublishResponseDTO
    {
        $this->ensureCapability('publish');
        $this->ensureValidAccount();

        $this->logInfo('Publishing to Instagram (simulated)', [
            'post_id' => $post->id,
        ]);

        // Simulate three-step flow: create container → poll status → publish
        return $this->simulatePublishFlow($post);
    }

    private function simulatePublishFlow(Post $post): PublishResponseDTO
    {
        $this->simulateNetworkDelay();

        // Simulate container creation
        $containerId = 'ig_container_'.bin2hex(random_bytes(8));
        $this->logInfo('Container created (simulated)', [
            'post_id' => $post->id,
            'container_id' => $containerId,
        ]);

        // Simulate polling (0-1 attempts, then FINISHED)
        $pollAttempts = mt_rand(0, 1);
        for ($i = 0; $i < $pollAttempts; $i++) {
            usleep(500000); // 0.5s
        }
        $this->logInfo('Container status FINISHED (simulated)', [
            'post_id' => $post->id,
            'container_id' => $containerId,
            'poll_attempts' => $pollAttempts,
        ]);

        // Determine success or failure for final publish step
        $successRate = (float) config('social_providers.fake.success_rate', 0.85);
        $shouldSucceed = mt_rand(1, 100) <= (int) ($successRate * 100);

        if ($shouldSucceed) {
            $platformPostId = 'ig_post_'.bin2hex(random_bytes(8));
            $this->logInfo('Published to Instagram (simulated)', [
                'post_id' => $post->id,
                'platform_post_id' => $platformPostId,
                'latency_ms' => $this->lastDelayMs ?? null,
            ]);

            return $this->success(
                $platformPostId,
                'Simulated publish completed successfully.',
                [
                    'post_id' => $post->id,
                    'container_id' => $containerId,
                    'latency_ms' => $this->lastDelayMs ?? null,
                ],
            );
        }

        $this->logWarning('Publish failed (simulated)', [
            'post_id' => $post->id,
        ]);

        return $this->failure(
            'Simulated API error: rate limit or transient failure.',
            [
                'post_id' => $post->id,
                'container_id' => $containerId,
                'simulated_error' => true,
            ],
        );
    }

    /**
     * @return list<string>
     */
    public static function capabilities(): array
    {
        return config('social_providers.capabilities.instagram', ['publish']);
    }
}
