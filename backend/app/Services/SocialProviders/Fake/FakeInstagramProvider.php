<?php

namespace App\Services\SocialProviders\Fake;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\SocialProviders\Base\BaseSocialProvider;
use App\Services\SocialProviders\Contracts\FetchesComments;
use App\Services\SocialProviders\DTOs\CommentCollectionDTO;
use App\Services\SocialProviders\DTOs\CommentDTO;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;
use App\Services\SocialProviders\Fake\Concerns\SimulatesApiPublishing;
use Illuminate\Support\Carbon;

class FakeInstagramProvider extends BaseSocialProvider implements FetchesComments
{
    use SimulatesApiPublishing;

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

        return $this->simulatePublish($post);
    }

    /**
     * @return list<string>
     */
    public static function capabilities(): array
    {
        return config('social_providers.capabilities.instagram', ['publish']);
    }
}
