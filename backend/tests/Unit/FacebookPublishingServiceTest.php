<?php

namespace Tests\Unit;

use App\Enums\PostStatus;
use App\Enums\SocialAccountStatus;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SocialProviders\Facebook\FacebookPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookPublishingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(Workspace $workspace): SocialAccount
    {
        return SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => 'facebook',
            'provider_account_id' => 'page-1',
            'account_name' => 'My Page',
            'username' => 'My Page',
            'access_token' => 'page-access-token',
            'status' => SocialAccountStatus::Connected,
            'metadata' => ['page_id' => 'page-1'],
        ]);
    }

    private function makeWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'FB',
            'slug' => 'fb-'.uniqid(),
        ]);

        return [$user, $workspace];
    }

    public function test_publishes_text_post_to_page_feed(): void
    {
        config([
            'facebook.api_version' => 'v25.0',
            'facebook.graph_base_url' => 'https://graph.facebook.com',
        ]);

        Http::fake([
            'https://graph.facebook.com/v25.0/page-1/feed' => Http::response(['id' => 'page-1_post-123'], 200),
        ]);

        [$user, $workspace] = $this->makeWorkspace();
        $account = $this->makeAccount($workspace);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'content' => 'Hello Facebook',
            'media_id' => null,
            'status' => PostStatus::Processing,
        ]);

        $result = app(FacebookPublishingService::class)->publishPost($account, $post);

        $this->assertTrue($result->success);
        $this->assertSame('page-1_post-123', $result->platformPostId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v25.0/page-1/feed'
                && $request['message'] === 'Hello Facebook'
                && $request['access_token'] === 'page-access-token';
        });
    }

    public function test_publishes_image_post_to_photos_edge(): void
    {
        config([
            'app.url' => 'https://api.example.com',
            'facebook.api_version' => 'v25.0',
            'facebook.graph_base_url' => 'https://graph.facebook.com',
            'facebook.media_public_base_url' => 'https://api.example.com/storage',
        ]);

        Http::fake([
            'https://graph.facebook.com/v25.0/page-1/photos' => Http::response(
                ['id' => 'photo-1', 'post_id' => 'page-1_post-999'],
                200,
            ),
        ]);

        [$user, $workspace] = $this->makeWorkspace();
        $account = $this->makeAccount($workspace);

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
            'content' => 'Photo caption',
            'media_id' => $media->id,
            'status' => PostStatus::Processing,
        ]);

        $result = app(FacebookPublishingService::class)->publishPost($account, $post);

        $this->assertTrue($result->success);
        $this->assertSame('page-1_post-999', $result->platformPostId);

        Http::assertSent(function ($request) use ($workspace) {
            return $request->url() === 'https://graph.facebook.com/v25.0/page-1/photos'
                && $request['url'] === 'https://api.example.com/storage/media/'.$workspace->id.'/photo.jpg'
                && $request['caption'] === 'Photo caption';
        });
    }

    public function test_publishes_video_post_to_videos_edge(): void
    {
        config([
            'app.url' => 'https://api.example.com',
            'facebook.api_version' => 'v25.0',
            'facebook.graph_base_url' => 'https://graph.facebook.com',
            'facebook.media_public_base_url' => 'https://api.example.com/storage',
        ]);

        Http::fake([
            'https://graph.facebook.com/v25.0/page-1/videos' => Http::response(['id' => 'video-77'], 200),
        ]);

        [$user, $workspace] = $this->makeWorkspace();
        $account = $this->makeAccount($workspace);

        $video = Media::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'file_name' => 'clip.mp4',
            'file_path' => 'media/'.$workspace->id.'/clip.mp4',
            'file_type' => 'video/mp4',
            'file_size' => 524288,
            'mime_type' => 'video/mp4',
            'created_at' => now(),
        ]);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'content' => 'Video description',
            'media_id' => $video->id,
            'status' => PostStatus::Processing,
        ]);

        $result = app(FacebookPublishingService::class)->publishPost($account, $post);

        $this->assertTrue($result->success);
        $this->assertSame('video-77', $result->platformPostId);

        Http::assertSent(function ($request) use ($workspace) {
            return $request->url() === 'https://graph.facebook.com/v25.0/page-1/videos'
                && $request['file_url'] === 'https://api.example.com/storage/media/'.$workspace->id.'/clip.mp4'
                && $request['description'] === 'Video description';
        });
    }

    public function test_returns_failure_on_graph_error(): void
    {
        config([
            'facebook.api_version' => 'v25.0',
            'facebook.graph_base_url' => 'https://graph.facebook.com',
        ]);

        Http::fake([
            'https://graph.facebook.com/v25.0/page-1/feed' => Http::response(
                ['error' => ['message' => 'Invalid OAuth access token.']],
                400,
            ),
        ]);

        [$user, $workspace] = $this->makeWorkspace();
        $account = $this->makeAccount($workspace);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'content' => 'Will fail',
            'media_id' => null,
            'status' => PostStatus::Processing,
        ]);

        $result = app(FacebookPublishingService::class)->publishPost($account, $post);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid OAuth access token', $result->message);
    }

    public function test_rejects_empty_post_without_media_or_message(): void
    {
        config([
            'facebook.api_version' => 'v25.0',
            'facebook.graph_base_url' => 'https://graph.facebook.com',
        ]);

        Http::fake();

        [$user, $workspace] = $this->makeWorkspace();
        $account = $this->makeAccount($workspace);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'content' => null,
            'title' => null,
            'media_id' => null,
            'status' => PostStatus::Processing,
        ]);

        $result = app(FacebookPublishingService::class)->publishPost($account, $post);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('message or media', $result->message);
        Http::assertNothingSent();
    }
}
