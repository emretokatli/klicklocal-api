<?php

namespace Tests\Unit;

use App\Enums\PostStatus;
use App\Enums\SocialAccountStatus;
use App\Models\Media;
use App\Models\Post;
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

    public function test_publishes_image_post_to_instagram_graph(): void
    {
        config([
            'app.url' => 'https://api.example.com',
            'instagram.api_version' => 'v21.0',
            'instagram.graph_base_url' => 'https://graph.instagram.com',
            'instagram.media_public_base_url' => 'https://api.example.com/storage',
        ]);

        Http::fake([
            'https://graph.instagram.com/v21.0/ig-user-1/media' => Http::response(['id' => 'container-99']),
            'https://graph.instagram.com/v21.0/ig-user-1/media_publish' => Http::response(['id' => 'published-77']),
        ]);

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

        $result = app(InstagramPublishingService::class)->publishFeedPost($account, $post);

        $this->assertTrue($result->success);
        $this->assertSame('published-77', $result->platformPostId);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.instagram.com/v21.0/ig-user-1/media'
                && $request['image_url'] === 'https://api.example.com/storage/media/1/photo.jpg'
                && $request['caption'] === 'Hello Instagram';
        });
    }
}
