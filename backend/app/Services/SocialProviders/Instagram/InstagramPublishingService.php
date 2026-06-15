<?php

namespace App\Services\SocialProviders\Instagram;

use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\Media\MediaService;
use App\Services\Media\MediaUrlService;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;
use App\Services\SocialProviders\Exceptions\SocialProviderException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InstagramPublishingService
{
    private const CONTAINER_POLL_MAX_ATTEMPTS = 10;
    private const CONTAINER_POLL_INTERVAL_SECONDS = 3;

    public function __construct(
        private readonly MediaService $mediaService,
        private readonly InstagramPublishingQuotaService $quotaService,
        private readonly MediaUrlService $mediaUrl,
    ) {}

    public function publishPost(SocialAccount $account, Post $post): PublishResponseDTO
    {
        $this->quotaService->checkQuota($account);

        $igUserId = $this->resolveIgUserId($account);
        $accessToken = (string) $account->access_token;
        $caption = trim((string) ($post->content ?? $post->title ?? ''));

        if ($caption === '') {
            return PublishResponseDTO::failure('Instagram posts require a caption (post content).');
        }

        $isVideo = $this->isVideoPost($post);

        if ($isVideo) {
            return $this->publishReel($account, $post, $igUserId, $accessToken, $caption);
        }

        return $this->publishFeedImage($account, $post, $igUserId, $accessToken, $caption);
    }

    private function publishFeedImage(
        SocialAccount $account,
        Post $post,
        string $igUserId,
        string $accessToken,
        string $caption,
    ): PublishResponseDTO {
        $mediaUrl = $this->resolvePublicMediaUrl($post);

        if ($mediaUrl === null) {
            return PublishResponseDTO::failure(
                'Instagram feed posts require an image. Attach media to the post.',
            );
        }

        return $this->createAndPublishContainer(
            $igUserId,
            $accessToken,
            $account,
            $post,
            $caption,
            imageUrl: $mediaUrl,
        );
    }

    private function publishReel(
        SocialAccount $account,
        Post $post,
        string $igUserId,
        string $accessToken,
        string $caption,
    ): PublishResponseDTO {
        $videoUrl = $this->resolvePublicVideoUrl($post);

        if ($videoUrl === null) {
            return PublishResponseDTO::failure(
                'Instagram Reels require a video file. Attach a video to the post.',
            );
        }

        return $this->createAndPublishContainer(
            $igUserId,
            $accessToken,
            $account,
            $post,
            $caption,
            videoUrl: $videoUrl,
        );
    }

    private function createAndPublishContainer(
        string $igUserId,
        string $accessToken,
        SocialAccount $account,
        Post $post,
        string $caption,
        ?string $imageUrl = null,
        ?string $videoUrl = null,
    ): PublishResponseDTO {
        $version = config('instagram.api_version', 'v21.0');
        $base = rtrim((string) config('instagram.graph_base_url'), '/');

        // Step 1: Create media container
        $containerPayload = [
            'caption' => $caption,
            'access_token' => $accessToken,
        ];

        if ($videoUrl !== null) {
            $containerPayload['media_type'] = 'REELS';
            $containerPayload['video_url'] = $videoUrl;
        } elseif ($imageUrl !== null) {
            $containerPayload['image_url'] = $imageUrl;
        }

        try {
            $containerResponse = Http::timeout(90)->post(
                "{$base}/{$version}/{$igUserId}/media",
                $containerPayload,
            );
        } catch (RequestException $e) {
            throw SocialProviderException::networkError('instagram', $e->getMessage());
        }

        if (!$containerResponse->successful()) {
            $message = $this->extractGraphError($containerResponse->json(), $containerResponse->body());

            Log::error('Instagram media container creation failed', [
                'social_account_id' => $account->id,
                'post_id' => $post->id,
                'response' => $containerResponse->json(),
            ]);

            return PublishResponseDTO::failure($message, $containerResponse->json());
        }

        $containerId = (string) ($containerResponse->json('id') ?? '');

        if ($containerId === '') {
            return PublishResponseDTO::failure(
                'Instagram did not return a media container id.',
                $containerResponse->json(),
            );
        }

        // Step 2: Poll container status until FINISHED or ERROR
        try {
            $finished = $this->pollContainerStatus($base, $version, $igUserId, $containerId, $accessToken);

            if (!$finished) {
                Log::warning('Instagram container processing timeout', [
                    'social_account_id' => $account->id,
                    'post_id' => $post->id,
                    'container_id' => $containerId,
                ]);

                return PublishResponseDTO::failure(
                    'Container processing took too long. Please try again.',
                );
            }
        } catch (SocialProviderException $e) {
            return PublishResponseDTO::failure($e->getMessage());
        }

        // Step 3: Publish the container
        try {
            $publishResponse = Http::timeout(90)->post(
                "{$base}/{$version}/{$igUserId}/media_publish",
                [
                    'creation_id' => $containerId,
                    'access_token' => $accessToken,
                ],
            );
        } catch (RequestException $e) {
            throw SocialProviderException::networkError('instagram', $e->getMessage());
        }

        if (!$publishResponse->successful()) {
            $message = $this->extractGraphError($publishResponse->json(), $publishResponse->body());

            Log::error('Instagram media_publish failed', [
                'social_account_id' => $account->id,
                'post_id' => $post->id,
                'container_id' => $containerId,
                'response' => $publishResponse->json(),
            ]);

            return PublishResponseDTO::failure($message, $publishResponse->json());
        }

        $platformPostId = (string) ($publishResponse->json('id') ?? $containerId);

        Log::info('Instagram post published', [
            'social_account_id' => $account->id,
            'post_id' => $post->id,
            'platform_post_id' => $platformPostId,
            'media_type' => $videoUrl !== null ? 'reel' : 'image',
        ]);

        return PublishResponseDTO::success(
            $platformPostId,
            'Published to Instagram.',
            $publishResponse->json(),
        );
    }

    private function pollContainerStatus(
        string $base,
        string $version,
        string $igUserId,
        string $containerId,
        string $accessToken,
    ): bool {
        for ($attempt = 0; $attempt < self::CONTAINER_POLL_MAX_ATTEMPTS; $attempt++) {
            if ($attempt > 0) {
                sleep(self::CONTAINER_POLL_INTERVAL_SECONDS);
            }

            try {
                $response = Http::timeout(30)->get(
                    "{$base}/{$version}/{$igUserId}/media/{$containerId}",
                    ['fields' => 'id,status_code,status', 'access_token' => $accessToken],
                );
            } catch (RequestException $e) {
                throw SocialProviderException::networkError('instagram', $e->getMessage());
            }

            if (!$response->successful()) {
                $message = $this->extractGraphError($response->json(), $response->body());
                throw SocialProviderException::networkError('instagram', $message);
            }

            $statusCode = (string) ($response->json('status_code') ?? '');

            if ($statusCode === 'FINISHED') {
                return true;
            }

            if ($statusCode === 'ERROR') {
                $error = (string) ($response->json('status') ?? 'Unknown error');
                throw SocialProviderException::networkError('instagram', $error);
            }

            // PROCESSING — continue polling
        }

        return false;
    }

    private function resolveIgUserId(SocialAccount $account): string
    {
        $metadata = $account->metadata ?? [];
        $id = (string) ($metadata['instagram_user_id'] ?? $account->provider_account_id ?? '');

        if ($id === '') {
            throw SocialProviderException::configurationError(
                'instagram',
                'Missing Instagram user id on social account.',
            );
        }

        return $id;
    }

    private function resolvePublicMediaUrl(Post $post): ?string
    {
        if ($post->media_id === null) {
            return null;
        }

        $media = Media::query()->find($post->media_id);

        if ($media === null) {
            return null;
        }

        return $this->mediaUrl->publicUrl($media);
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

        if (!Str::startsWith($mime, 'video/')) {
            return null;
        }

        return $this->mediaUrl->publicUrl($media);
    }

    private function isVideoPost(Post $post): bool
    {
        if ($post->media_id === null) {
            return false;
        }

        $media = Media::query()->find($post->media_id);

        if ($media === null) {
            return false;
        }

        $mime = (string) ($media->mime_type ?? $media->file_type ?? '');

        return Str::startsWith($mime, 'video/');
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function extractGraphError(?array $json, string $fallbackBody): string
    {
        if (isset($json['error']['message'])) {
            return (string) $json['error']['message'];
        }

        if (isset($json['error_message'])) {
            return (string) $json['error_message'];
        }

        return Str::limit($fallbackBody, 500);
    }
}
