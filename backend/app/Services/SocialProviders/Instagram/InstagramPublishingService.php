<?php

namespace App\Services\SocialProviders\Instagram;

use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\Media\MediaService;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;
use App\Services\SocialProviders\Exceptions\SocialProviderException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InstagramPublishingService
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {}

    public function publishFeedPost(SocialAccount $account, Post $post): PublishResponseDTO
    {
        $igUserId = $this->resolveIgUserId($account);
        $accessToken = (string) $account->access_token;
        $caption = trim((string) ($post->content ?? $post->title ?? ''));

        if ($caption === '') {
            return PublishResponseDTO::failure('Instagram posts require a caption (post content).');
        }

        $mediaUrl = $this->resolvePublicMediaUrl($post);

        if ($mediaUrl === null) {
            return PublishResponseDTO::failure(
                'Instagram feed posts require an image. Attach media to the post (media_id).',
            );
        }

        if ($this->isVideoPost($post)) {
            return PublishResponseDTO::failure(
                'Video/Reels publishing is not implemented yet. Use a JPEG or PNG image.',
            );
        }

        $version = config('instagram.api_version', 'v21.0');
        $base = rtrim((string) config('instagram.graph_base_url'), '/');

        try {
            $containerResponse = Http::timeout(90)->post(
                "{$base}/{$version}/{$igUserId}/media",
                [
                    'image_url' => $mediaUrl,
                    'caption' => $caption,
                    'access_token' => $accessToken,
                ],
            );
        } catch (RequestException $e) {
            throw SocialProviderException::networkError('instagram', $e->getMessage());
        }

        if (! $containerResponse->successful()) {
            $message = $this->extractGraphError($containerResponse->json(), $containerResponse->body());

            Log::error('Instagram media container failed', [
                'social_account_id' => $account->id,
                'post_id' => $post->id,
                'response' => $containerResponse->json(),
            ]);

            return PublishResponseDTO::failure($message, $containerResponse->json());
        }

        $creationId = (string) ($containerResponse->json('id') ?? '');

        if ($creationId === '') {
            return PublishResponseDTO::failure(
                'Instagram did not return a media container id.',
                $containerResponse->json(),
            );
        }

        try {
            $publishResponse = Http::timeout(90)->post(
                "{$base}/{$version}/{$igUserId}/media_publish",
                [
                    'creation_id' => $creationId,
                    'access_token' => $accessToken,
                ],
            );
        } catch (RequestException $e) {
            throw SocialProviderException::networkError('instagram', $e->getMessage());
        }

        if (! $publishResponse->successful()) {
            $message = $this->extractGraphError($publishResponse->json(), $publishResponse->body());

            Log::error('Instagram media_publish failed', [
                'social_account_id' => $account->id,
                'post_id' => $post->id,
                'creation_id' => $creationId,
                'response' => $publishResponse->json(),
            ]);

            return PublishResponseDTO::failure($message, $publishResponse->json());
        }

        $platformPostId = (string) ($publishResponse->json('id') ?? $creationId);

        Log::info('Instagram feed post published', [
            'social_account_id' => $account->id,
            'post_id' => $post->id,
            'platform_post_id' => $platformPostId,
        ]);

        return PublishResponseDTO::success(
            $platformPostId,
            'Published to Instagram.',
            $publishResponse->json(),
        );
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

        $base = rtrim(
            (string) config('instagram.media_public_base_url', config('app.url').'/storage'),
            '/',
        );

        return $base.'/'.$media->file_path;
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
