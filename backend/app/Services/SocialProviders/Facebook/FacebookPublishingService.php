<?php

namespace App\Services\SocialProviders\Facebook;

use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\Media\MediaUrlService;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;
use App\Services\SocialProviders\Exceptions\SocialProviderException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Facebook Page publishing. Routes a post to the correct Graph edge:
 * - text only          -> POST /{page-id}/feed
 * - image attached     -> POST /{page-id}/photos
 * - video attached     -> POST /{page-id}/videos
 */
class FacebookPublishingService
{
    public function __construct(
        private readonly MediaUrlService $mediaUrl,
    ) {}

    public function publishPost(SocialAccount $account, Post $post): PublishResponseDTO
    {
        $pageId = $this->resolvePageId($account);
        $pageAccessToken = (string) $account->access_token;
        $message = trim((string) ($post->content ?? $post->title ?? ''));

        $media = $this->resolveMedia($post);

        if ($media === null) {
            if ($message === '') {
                return PublishResponseDTO::failure('Facebook posts require a message or media.');
            }

            return $this->publishFeed($account, $post, $pageId, $pageAccessToken, $message);
        }

        $mediaUrl = $this->publicMediaUrl($media);
        $mime = (string) ($media->mime_type ?? $media->file_type ?? '');

        if (Str::startsWith($mime, 'video/')) {
            return $this->publishVideo($account, $post, $pageId, $pageAccessToken, $message, $mediaUrl);
        }

        return $this->publishPhoto($account, $post, $pageId, $pageAccessToken, $message, $mediaUrl);
    }

    private function publishFeed(
        SocialAccount $account,
        Post $post,
        string $pageId,
        string $token,
        string $message,
    ): PublishResponseDTO {
        return $this->postToEdge(
            $account,
            $post,
            "{$pageId}/feed",
            [
                'message' => $message,
                'access_token' => $token,
            ],
            'feed',
        );
    }

    private function publishPhoto(
        SocialAccount $account,
        Post $post,
        string $pageId,
        string $token,
        string $message,
        string $imageUrl,
    ): PublishResponseDTO {
        return $this->postToEdge(
            $account,
            $post,
            "{$pageId}/photos",
            array_filter([
                'url' => $imageUrl,
                'caption' => $message !== '' ? $message : null,
                'access_token' => $token,
            ], static fn ($v) => $v !== null),
            'photo',
        );
    }

    private function publishVideo(
        SocialAccount $account,
        Post $post,
        string $pageId,
        string $token,
        string $message,
        string $videoUrl,
    ): PublishResponseDTO {
        return $this->postToEdge(
            $account,
            $post,
            "{$pageId}/videos",
            array_filter([
                'file_url' => $videoUrl,
                'description' => $message !== '' ? $message : null,
                'access_token' => $token,
            ], static fn ($v) => $v !== null),
            'video',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postToEdge(
        SocialAccount $account,
        Post $post,
        string $edge,
        array $payload,
        string $kind,
    ): PublishResponseDTO {
        $version = config('facebook.api_version', 'v25.0');
        $base = rtrim((string) config('facebook.graph_base_url'), '/');
        $url = "{$base}/{$version}/{$edge}";

        try {
            $response = Http::timeout(120)->asForm()->post($url, $payload);
        } catch (RequestException $e) {
            throw SocialProviderException::networkError('facebook', $e->getMessage());
        }

        if (! $response->successful() || $response->json('error')) {
            $message = $this->extractGraphError($response->json(), $response->body());

            Log::error('Facebook publish failed', [
                'social_account_id' => $account->id,
                'post_id' => $post->id,
                'kind' => $kind,
                'response' => $response->json(),
            ]);

            return PublishResponseDTO::failure($message, $response->json());
        }

        // Edges return different id keys: feed/photos -> id/post_id, videos -> id
        $platformPostId = (string) (
            $response->json('post_id')
            ?? $response->json('id')
            ?? ''
        );

        if ($platformPostId === '') {
            return PublishResponseDTO::failure(
                'Facebook did not return a post id.',
                $response->json(),
            );
        }

        Log::info('Facebook post published', [
            'social_account_id' => $account->id,
            'post_id' => $post->id,
            'platform_post_id' => $platformPostId,
            'kind' => $kind,
        ]);

        return PublishResponseDTO::success(
            $platformPostId,
            'Published to Facebook.',
            $response->json(),
        );
    }

    private function resolvePageId(SocialAccount $account): string
    {
        $metadata = $account->metadata ?? [];
        $id = (string) ($metadata['page_id'] ?? $account->provider_account_id ?? '');

        if ($id === '') {
            throw SocialProviderException::configurationError(
                'facebook',
                'Missing Facebook Page id on social account.',
            );
        }

        return $id;
    }

    private function resolveMedia(Post $post): ?Media
    {
        if ($post->media_id === null) {
            return null;
        }

        return Media::query()->find($post->media_id);
    }

    private function publicMediaUrl(Media $media): string
    {
        return $this->mediaUrl->publicUrl($media);
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function extractGraphError(?array $json, string $fallbackBody): string
    {
        if (isset($json['error']['message'])) {
            return (string) $json['error']['message'];
        }

        return Str::limit($fallbackBody, 500);
    }
}
