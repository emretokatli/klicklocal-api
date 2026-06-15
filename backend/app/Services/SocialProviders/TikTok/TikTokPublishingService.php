<?php

namespace App\Services\SocialProviders\TikTok;

use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\Media\MediaUrlService;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;
use App\Services\SocialProviders\Exceptions\SocialProviderException;
use App\Services\SocialProviders\TikTok\DTOs\TikTokCreatorInfoDTO;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * TikTok Content Posting API (direct post) publishing, aligned with the audit
 * requirements:
 *   1. query creator_info before every post and honour privacy_level_options
 *   2. video/init with PULL_FROM_URL, then poll status/fetch until complete
 *   3. while the app is unaudited (config tiktok.audited = false) force the
 *      post to SELF_ONLY and disable branded-content.
 */
class TikTokPublishingService
{
    public const PRIVACY_SELF_ONLY = 'SELF_ONLY';

    public function __construct(
        private readonly MediaUrlService $mediaUrl,
    ) {}

    public function publishPost(SocialAccount $account, Post $post): PublishResponseDTO
    {
        $accessToken = (string) $account->access_token;

        $videoUrl = $this->resolvePublicVideoUrl($post);

        if ($videoUrl === null) {
            return PublishResponseDTO::failure(
                'TikTok posts require a public video. Attach a video to the post.',
            );
        }

        try {
            $creatorInfo = $this->queryCreatorInfo($account);
        } catch (SocialProviderException $e) {
            return PublishResponseDTO::failure($e->getMessage());
        }

        $options = $this->resolvePostOptions($post, $creatorInfo);

        $title = trim((string) ($post->content ?? $post->title ?? ''));

        $postInfo = [
            'title' => $title,
            'privacy_level' => $options['privacy_level'],
            'disable_comment' => $options['disable_comment'],
            'disable_duet' => $options['disable_duet'],
            'disable_stitch' => $options['disable_stitch'],
            'brand_content_toggle' => $options['brand_content_toggle'],
            'brand_organic_toggle' => $options['brand_organic_toggle'],
        ];

        // Step 1: initialise the PULL_FROM_URL publish
        try {
            $initResponse = Http::withToken($accessToken)
                ->timeout(60)
                ->asJson()
                ->post(config('tiktok.video_init_url'), [
                    'post_info' => $postInfo,
                    'source_info' => [
                        'source' => 'PULL_FROM_URL',
                        'video_url' => $videoUrl,
                    ],
                ]);
        } catch (RequestException $e) {
            throw SocialProviderException::networkError('tiktok', $e->getMessage());
        }

        if (! $this->isOk($initResponse->json()) || ! $initResponse->successful()) {
            $message = $this->extractError($initResponse->json(), $initResponse->body());

            Log::error('TikTok video/init failed', [
                'social_account_id' => $account->id,
                'post_id' => $post->id,
                'response' => $initResponse->json(),
            ]);

            return PublishResponseDTO::failure($message, $initResponse->json());
        }

        $publishId = (string) ($initResponse->json('data.publish_id') ?? '');

        if ($publishId === '') {
            return PublishResponseDTO::failure(
                'TikTok did not return a publish id.',
                $initResponse->json(),
            );
        }

        // Step 2: poll status until complete or failed
        try {
            $finalStatus = $this->pollPublishStatus($accessToken, $publishId, $account, $post);
        } catch (SocialProviderException $e) {
            return PublishResponseDTO::failure($e->getMessage());
        }

        if ($finalStatus === null) {
            return PublishResponseDTO::failure(
                'TikTok post processing took too long. It may still complete on TikTok.',
                ['publish_id' => $publishId],
            );
        }

        Log::info('TikTok post published', [
            'social_account_id' => $account->id,
            'post_id' => $post->id,
            'publish_id' => $publishId,
            'privacy_level' => $options['privacy_level'],
        ]);

        $message = 'Published to TikTok.';
        if ($options['privacy_level'] === self::PRIVACY_SELF_ONLY && ! (bool) config('tiktok.audited', false)) {
            // Surface why the post is private: the app is not yet audited.
            $message .= ' Hinweis: Da die TikTok-App noch nicht freigegeben ist, '
                .'wurde der Beitrag als „Nur ich" (privat) veröffentlicht.';
            $finalStatus['warning'] = 'forced_self_only';
        }

        return PublishResponseDTO::success(
            $publishId,
            $message,
            $finalStatus,
        );
    }

    public function queryCreatorInfo(SocialAccount $account): TikTokCreatorInfoDTO
    {
        $accessToken = (string) $account->access_token;

        try {
            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->asJson()
                ->post(config('tiktok.creator_info_url'), (object) []);
        } catch (RequestException $e) {
            throw SocialProviderException::networkError('tiktok', $e->getMessage());
        }

        if (! $this->isOk($response->json()) || ! $response->successful()) {
            $message = $this->extractError($response->json(), $response->body());
            throw SocialProviderException::networkError('tiktok', $message);
        }

        return TikTokCreatorInfoDTO::fromResponse($response->json());
    }

    /**
     * Resolve the effective post options, enforcing the audit gate.
     *
     * @return array{
     *     privacy_level: string,
     *     disable_comment: bool,
     *     disable_duet: bool,
     *     disable_stitch: bool,
     *     brand_content_toggle: bool,
     *     brand_organic_toggle: bool
     * }
     */
    private function resolvePostOptions(Post $post, TikTokCreatorInfoDTO $creatorInfo): array
    {
        $requested = (array) (($post->metadata['tiktok'] ?? []) ?: []);

        $audited = (bool) config('tiktok.audited', false);

        $brandContent = (bool) ($requested['brand_content_toggle'] ?? false);
        $brandOrganic = (bool) ($requested['brand_organic_toggle'] ?? false);

        if (! $audited) {
            // Unaudited apps may only create private posts and cannot mark
            // branded content.
            $privacy = self::PRIVACY_SELF_ONLY;
            $brandContent = false;
        } else {
            $requestedPrivacy = (string) ($requested['privacy_level'] ?? '');
            $allowed = $creatorInfo->privacyLevelOptions;

            // Only accept a privacy level the creator_info actually allows.
            if ($requestedPrivacy !== '' && in_array($requestedPrivacy, $allowed, true)) {
                $privacy = $requestedPrivacy;
            } else {
                $privacy = $allowed[0] ?? self::PRIVACY_SELF_ONLY;
            }

            // TikTok rule: branded content cannot be posted as private.
            if ($brandContent && $privacy === self::PRIVACY_SELF_ONLY) {
                $privacy = $allowed[0] ?? self::PRIVACY_SELF_ONLY;
            }
        }

        return [
            'privacy_level' => $privacy,
            'disable_comment' => (bool) ($requested['disable_comment'] ?? false) || $creatorInfo->commentDisabled,
            'disable_duet' => (bool) ($requested['disable_duet'] ?? false) || $creatorInfo->duetDisabled,
            'disable_stitch' => (bool) ($requested['disable_stitch'] ?? false) || $creatorInfo->stitchDisabled,
            'brand_content_toggle' => $brandContent,
            'brand_organic_toggle' => $brandOrganic,
        ];
    }

    /**
     * @return array<string, mixed>|null  the final status payload, or null on timeout
     */
    private function pollPublishStatus(
        string $accessToken,
        string $publishId,
        SocialAccount $account,
        Post $post,
    ): ?array {
        $maxAttempts = (int) config('tiktok.publish_poll_max_attempts', 20);
        $interval = (int) config('tiktok.publish_poll_interval_seconds', 3);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if ($attempt > 0) {
                sleep($interval);
            }

            try {
                $response = Http::withToken($accessToken)
                    ->timeout(30)
                    ->asJson()
                    ->post(config('tiktok.status_fetch_url'), [
                        'publish_id' => $publishId,
                    ]);
            } catch (RequestException $e) {
                throw SocialProviderException::networkError('tiktok', $e->getMessage());
            }

            if (! $this->isOk($response->json()) || ! $response->successful()) {
                $message = $this->extractError($response->json(), $response->body());
                throw SocialProviderException::networkError('tiktok', $message);
            }

            $status = (string) ($response->json('data.status') ?? '');

            if ($status === 'PUBLISH_COMPLETE') {
                return (array) $response->json('data', []);
            }

            if ($status === 'FAILED') {
                $reason = (string) ($response->json('data.fail_reason') ?? 'Unknown failure');
                throw SocialProviderException::networkError('tiktok', "Publish failed: {$reason}");
            }

            // PROCESSING_UPLOAD / PROCESSING_DOWNLOAD / SEND_TO_USER_INBOX — keep polling
        }

        return null;
    }

    private function resolvePublicVideoUrl(Post $post): ?string
    {
        if ($post->media_id === null) {
            return null;
        }

        $media = Media::query()->find($post->media_id);

        if ($media === null) {
            return null;
        }

        $mime = (string) ($media->mime_type ?? $media->file_type ?? '');

        if (! Str::startsWith($mime, 'video/')) {
            return null;
        }

        return $this->mediaUrl->publicUrl($media);
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function isOk(?array $json): bool
    {
        $code = $json['error']['code'] ?? null;

        return $code === null || $code === 'ok';
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function extractError(?array $json, string $fallbackBody): string
    {
        if (isset($json['error']['message']) && $json['error']['message'] !== '') {
            return (string) $json['error']['message'];
        }

        if (isset($json['error']['code']) && $json['error']['code'] !== 'ok') {
            return (string) $json['error']['code'];
        }

        return Str::limit($fallbackBody, 500);
    }
}
