<?php

namespace Tests\Unit;

use App\Enums\PostStatus;
use App\Enums\SocialAccountStatus;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SocialProviders\TikTok\TikTokPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TikTokPublishingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function baseConfig(bool $audited): void
    {
        config([
            'app.url' => 'https://api.example.com',
            'tiktok.audited' => $audited,
            'tiktok.creator_info_url' => 'https://open.tiktokapis.com/v2/post/publish/creator_info/query/',
            'tiktok.video_init_url' => 'https://open.tiktokapis.com/v2/post/publish/video/init/',
            'tiktok.status_fetch_url' => 'https://open.tiktokapis.com/v2/post/publish/status/fetch/',
            'tiktok.media_public_base_url' => 'https://api.example.com/storage',
            'tiktok.publish_poll_interval_seconds' => 0,
            'tiktok.publish_poll_max_attempts' => 5,
        ]);
    }

    private function makePostWithVideo(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'TT',
            'slug' => 'tt-'.uniqid(),
        ]);

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

        $account = SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => 'tiktok',
            'provider_account_id' => 'open-id-1',
            'account_name' => 'Creator',
            'username' => 'Creator',
            'access_token' => 'tt-token',
            'status' => SocialAccountStatus::Connected,
            'metadata' => ['open_id' => 'open-id-1'],
        ]);

        return [$user, $workspace, $video, $account];
    }

    public function test_unaudited_forces_self_only_and_completes(): void
    {
        $this->baseConfig(audited: false);

        $captured = [];
        Http::fake(function ($request) use (&$captured) {
            $url = $request->url();
            if (str_contains($url, 'creator_info/query')) {
                return Http::response(['data' => ['privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'SELF_ONLY']], 'error' => ['code' => 'ok']]);
            }
            if (str_contains($url, 'video/init')) {
                $captured['init'] = $request->data();
                return Http::response(['data' => ['publish_id' => 'pub-1'], 'error' => ['code' => 'ok']]);
            }
            if (str_contains($url, 'status/fetch')) {
                return Http::response(['data' => ['status' => 'PUBLISH_COMPLETE'], 'error' => ['code' => 'ok']]);
            }
            return Http::response([], 200);
        });

        [, , , $account] = $this->makePostWithVideo();
        $post = Post::create([
            'workspace_id' => $account->workspace_id,
            'user_id' => $account->workspace->owner_id,
            'content' => 'Mein Reel',
            'media_id' => Media::query()->first()->id,
            'metadata' => ['tiktok' => ['privacy_level' => 'PUBLIC_TO_EVERYONE']],
            'status' => PostStatus::Processing,
        ]);

        $result = app(TikTokPublishingService::class)->publishPost($account, $post);

        $this->assertTrue($result->success);
        $this->assertSame('pub-1', $result->platformPostId);
        // Audit gate must override the requested PUBLIC_TO_EVERYONE.
        $this->assertSame('SELF_ONLY', $captured['init']['post_info']['privacy_level']);
        $this->assertFalse($captured['init']['post_info']['brand_content_toggle']);
        $this->assertSame('PULL_FROM_URL', $captured['init']['source_info']['source']);
    }

    public function test_audited_honours_requested_privacy_from_options(): void
    {
        $this->baseConfig(audited: true);

        $captured = [];
        Http::fake(function ($request) use (&$captured) {
            $url = $request->url();
            if (str_contains($url, 'creator_info/query')) {
                return Http::response(['data' => ['privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY']], 'error' => ['code' => 'ok']]);
            }
            if (str_contains($url, 'video/init')) {
                $captured['init'] = $request->data();
                return Http::response(['data' => ['publish_id' => 'pub-2'], 'error' => ['code' => 'ok']]);
            }
            if (str_contains($url, 'status/fetch')) {
                return Http::response(['data' => ['status' => 'PUBLISH_COMPLETE'], 'error' => ['code' => 'ok']]);
            }
            return Http::response([], 200);
        });

        [, , , $account] = $this->makePostWithVideo();
        $post = Post::create([
            'workspace_id' => $account->workspace_id,
            'user_id' => $account->workspace->owner_id,
            'content' => 'Mein Reel',
            'media_id' => Media::query()->first()->id,
            'metadata' => ['tiktok' => ['privacy_level' => 'FOLLOWER_OF_CREATOR']],
            'status' => PostStatus::Processing,
        ]);

        $result = app(TikTokPublishingService::class)->publishPost($account, $post);

        $this->assertTrue($result->success);
        $this->assertSame('FOLLOWER_OF_CREATOR', $captured['init']['post_info']['privacy_level']);
    }

    public function test_returns_failure_when_status_failed(): void
    {
        $this->baseConfig(audited: true);

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'creator_info/query')) {
                return Http::response(['data' => ['privacy_level_options' => ['PUBLIC_TO_EVERYONE']], 'error' => ['code' => 'ok']]);
            }
            if (str_contains($url, 'video/init')) {
                return Http::response(['data' => ['publish_id' => 'pub-3'], 'error' => ['code' => 'ok']]);
            }
            if (str_contains($url, 'status/fetch')) {
                return Http::response(['data' => ['status' => 'FAILED', 'fail_reason' => 'video_format_check_failed'], 'error' => ['code' => 'ok']]);
            }
            return Http::response([], 200);
        });

        [, , , $account] = $this->makePostWithVideo();
        $post = Post::create([
            'workspace_id' => $account->workspace_id,
            'user_id' => $account->workspace->owner_id,
            'content' => 'Mein Reel',
            'media_id' => Media::query()->first()->id,
            'status' => PostStatus::Processing,
        ]);

        $result = app(TikTokPublishingService::class)->publishPost($account, $post);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('video_format_check_failed', $result->message);
    }

    public function test_rejects_post_without_video(): void
    {
        $this->baseConfig(audited: false);
        Http::fake();

        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'TT',
            'slug' => 'tt-novideo',
        ]);
        $account = SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => 'tiktok',
            'provider_account_id' => 'open-id-2',
            'username' => 'Creator',
            'access_token' => 'tt-token',
            'status' => SocialAccountStatus::Connected,
        ]);

        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'content' => 'No video',
            'media_id' => null,
            'status' => PostStatus::Processing,
        ]);

        $result = app(TikTokPublishingService::class)->publishPost($account, $post);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('require a public video', $result->message);
        Http::assertNothingSent();
    }
}
