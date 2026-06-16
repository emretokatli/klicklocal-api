<?php

namespace Tests\Unit;

use App\Enums\PostStatus;
use App\Enums\PostPlatformStatus;
use App\Enums\SocialAccountStatus;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SocialProviders\Instagram\InstagramPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramPublishingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_image_post_with_container_flow(): void
    {
        config([
            'app.url' => 'https://api.example.com',
            'instagram.api_version' => 'v21.0',
            'instagram.graph_base_url' => 'https://graph.instagram.com',
            'instagram.media_public_base_url' => 'https://api.example.com/storage',
        ]);

        Http::fake(function ($request) {
            if ($request->url() === 'https://graph.instagram.com/v21.0/ig-user-1/media') {
                return Http::response(['id' => 'container-99'], 200);
            }

            if (str_contains($request->url(), 'https://graph.instagram.com/v21.0/ig-user-1/media/container-99')) {
                return Http::response(['id' => 'container-99', 'status_code' => 'FINISHED'], 200);
            }

            if ($request->url() === 'https://graph.instagram.com/v21.0/ig-user-1/media_publish') {
                return Http::response(['id' => 'published-77'], 200);
            }

            return Http::response([], 200);
        });

        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Pub',
            'slug' => 'pub-ig',
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

        $account = SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => 'instagram',
            'provider_account_id' => 'ig-user-1',
            'username' => '@brand',
            'access_token' => 'long-lived-token',
            'status' => SocialAccountStatus::Connected,
            'metadata' => ['instagram_user_id' => 'ig-user-1'],
        ]);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'content' => 'Hello Instagram',
            'media_id' => $media->id,
            'status' => PostStatus::Processing,
        ]);

        $result = app(InstagramPublishingService::class)->publishPost($account, $post);

        $this->assertTrue($result->success);
        $this->assertSame('published-77', $result->platformPostId);

        // Verify container creation request
        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.instagram.com/v21.0/ig-user-1/media';
        });

        // Verify poll request (GET with fields param)
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://graph.instagram.com/v21.0/ig-user-1/media/container-99')
                && str_contains($request->url(), 'fields=id%2Cstatus_code%2Cstatus');
        });

        // Verify publish request
        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.instagram.com/v21.0/ig-user-1/media_publish';
        });
    }

    public function test_publishes_reel_with_video_url(): void
    {
        config([
            'app.url' => 'https://api.example.com',
            'instagram.api_version' => 'v21.0',
            'instagram.graph_base_url' => 'https://graph.instagram.com',
            'instagram.media_public_base_url' => 'https://api.example.com/storage',
        ]);

        Http::fake(function ($request) {
            if ($request->url() === 'https://graph.instagram.com/v21.0/ig-user-2/media') {
                return Http::response(['id' => 'reel-container-55'], 200);
            }

            if (str_contains($request->url(), 'https://graph.instagram.com/v21.0/ig-user-2/media/reel-container-55')) {
                return Http::response(['id' => 'reel-container-55', 'status_code' => 'FINISHED'], 200);
            }

            if ($request->url() === 'https://graph.instagram.com/v21.0/ig-user-2/media_publish') {
                return Http::response(['id' => 'reel-published-88'], 200);
            }

            return Http::response([], 200);
        });

        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'VideoStudio',
            'slug' => 'video-studio',
        ]);

        $video = Media::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'file_name' => 'reel.mp4',
            'file_path' => 'media/'.$workspace->id.'/reel.mp4',
            'file_type' => 'video/mp4',
            'file_size' => 5242880,
            'mime_type' => 'video/mp4',
            'created_at' => now(),
        ]);

        $account = SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => 'instagram',
            'provider_account_id' => 'ig-user-2',
            'username' => '@filmmaker',
            'access_token' => 'long-lived-token',
            'status' => SocialAccountStatus::Connected,
            'metadata' => ['instagram_user_id' => 'ig-user-2'],
        ]);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'content' => 'Check out this Reel!',
            'media_id' => $video->id,
            'status' => PostStatus::Processing,
        ]);

        $result = app(InstagramPublishingService::class)->publishPost($account, $post);

        $this->assertTrue($result->success);
        $this->assertSame('reel-published-88', $result->platformPostId);

        // Verify reel container creation request (POST to media endpoint)
        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.instagram.com/v21.0/ig-user-2/media';
        });
    }

    public function test_rejects_publish_when_quota_exceeded(): void
    {
        config([
            'app.url' => 'https://api.example.com',
            'instagram.api_version' => 'v21.0',
            'instagram.graph_base_url' => 'https://graph.instagram.com',
            'instagram.media_public_base_url' => 'https://api.example.com/storage',
        ]);

        Http::fake();

        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'HighVolume',
            'slug' => 'high-volume',
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

        $account = SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => 'instagram',
            'provider_account_id' => 'ig-user-quota',
            'username' => '@quotauser',
            'access_token' => 'token-quota',
            'status' => SocialAccountStatus::Connected,
            'metadata' => ['instagram_user_id' => 'ig-user-quota'],
        ]);

        // Create 25 published posts in the last 24 hours
        for ($i = 0; $i < 25; $i++) {
            $post = Post::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'content' => "Post {$i}",
                'media_id' => $media->id,
                'status' => PostStatus::Published,
            ]);

            PostPlatform::create([
                'post_id' => $post->id,
                'social_account_id' => $account->id,
                'status' => PostPlatformStatus::Published,
                'platform_post_id' => "ig_post_{$i}",
                'published_at' => now()->subHours(12),
            ]);
        }

        // Try to publish one more
        $newPost = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'content' => 'This should fail',
            'media_id' => $media->id,
            'status' => PostStatus::Processing,
        ]);

        $this->expectException(\App\Services\SocialProviders\Exceptions\SocialProviderException::class);
        $this->expectExceptionMessage('quota exceeded');

        app(InstagramPublishingService::class)->publishPost($account, $newPost);

        Http::assertNothingSent();
    }

    public function test_fails_when_container_status_timeout(): void
    {
        config([
            'app.url' => 'https://api.example.com',
            'instagram.api_version' => 'v21.0',
            'instagram.graph_base_url' => 'https://graph.instagram.com',
            'instagram.media_public_base_url' => 'https://api.example.com/storage',
        ]);

        Http::fake(function ($request) {
            if ($request->url() === 'https://graph.instagram.com/v21.0/ig-user-1/media') {
                return Http::response(['id' => 'timeout-container-1'], 200);
            }

            if (str_contains($request->url(), 'https://graph.instagram.com/v21.0/ig-user-1/media/timeout-container-1')) {
                // Always return PROCESSING to simulate timeout
                return Http::response(['id' => 'timeout-container-1', 'status_code' => 'PROCESSING'], 200);
            }

            return Http::response([], 200);
        });

        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'SlowServer',
            'slug' => 'slow-server',
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

        $account = SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => 'instagram',
            'provider_account_id' => 'ig-user-1',
            'username' => '@brand',
            'access_token' => 'long-lived-token',
            'status' => SocialAccountStatus::Connected,
            'metadata' => ['instagram_user_id' => 'ig-user-1'],
        ]);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'content' => 'Will timeout',
            'media_id' => $media->id,
            'status' => PostStatus::Processing,
        ]);

        $result = app(InstagramPublishingService::class)->publishPost($account, $post);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('processing took too long', strtolower($result->message));
    }

    public function test_rejects_post_without_caption(): void
    {
        config([
            'instagram.api_version' => 'v21.0',
            'instagram.graph_base_url' => 'https://graph.instagram.com',
        ]);

        Http::fake();

        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'NoCaptions',
            'slug' => 'no-captions',
        ]);

        $account = SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => 'instagram',
            'provider_account_id' => 'ig-user-1',
            'username' => '@brand',
            'access_token' => 'token',
            'status' => SocialAccountStatus::Connected,
            'metadata' => ['instagram_user_id' => 'ig-user-1'],
        ]);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'content' => null,
            'title' => null,
            'status' => PostStatus::Processing,
        ]);

        $result = app(InstagramPublishingService::class)->publishPost($account, $post);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('caption', strtolower($result->message));
        Http::assertNothingSent();
    }

    public function test_rejects_image_post_without_media(): void
    {
        config([
            'instagram.api_version' => 'v21.0',
            'instagram.graph_base_url' => 'https://graph.instagram.com',
        ]);

        Http::fake();

        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'NoMedia',
            'slug' => 'no-media',
        ]);

        $account = SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => 'instagram',
            'provider_account_id' => 'ig-user-1',
            'username' => '@brand',
            'access_token' => 'token',
            'status' => SocialAccountStatus::Connected,
            'metadata' => ['instagram_user_id' => 'ig-user-1'],
        ]);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'content' => 'Text only',
            'media_id' => null,
            'status' => PostStatus::Processing,
        ]);

        $result = app(InstagramPublishingService::class)->publishPost($account, $post);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('require an image', $result->message);
        Http::assertNothingSent();
    }
}
