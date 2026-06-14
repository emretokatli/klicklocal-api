<?php

namespace Tests\Feature;

use App\Enums\PostPlatformStatus;
use App\Enums\PostStatus;
use App\Enums\SocialAccountStatus;
use App\Enums\WorkspaceRole;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Comments\CommentSyncService;
use App\Services\SocialProviders\Base\BaseSocialProvider;
use App\Services\SocialProviders\Contracts\FetchesComments;
use App\Services\SocialProviders\DTOs\CommentCollectionDTO;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;
use App\Services\SocialProviders\Exceptions\SocialProviderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentSyncTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspace(User $user): Workspace
    {
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Test WS',
            'slug' => 'test-ws',
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner,
            'created_at' => now(),
        ]);

        return $workspace;
    }

    private function authHeaders(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }

    private function createConnectedAccount(Workspace $workspace, string $provider = 'instagram'): SocialAccount
    {
        return SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => $provider,
            'provider_account_id' => '1789'.$workspace->id,
            'account_name' => 'Test Account',
            'username' => 'test_account',
            'status' => SocialAccountStatus::Connected,
            'access_token' => 'fake-token',
        ]);
    }

    private function createPublishedPost(
        Workspace $workspace,
        SocialAccount $account,
        string $platformPostId,
    ): Post {
        $post = Post::create([
            'workspace_id' => $workspace->id,
            'user_id' => $workspace->owner_id,
            'title' => 'Published post',
            'content' => 'Hello world',
            'status' => PostStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        PostPlatform::create([
            'post_id' => $post->id,
            'social_account_id' => $account->id,
            'status' => PostPlatformStatus::Published,
            'platform_post_id' => $platformPostId,
            'published_at' => now()->subDay(),
            'created_at' => now()->subDay(),
        ]);

        return $post;
    }

    public function test_sync_creates_comments_linked_to_post_and_workspace(): void
    {
        config()->set('social_providers.drivers.instagram', 'fake');

        $user = User::factory()->create();
        $workspace = $this->setupWorkspace($user);
        $account = $this->createConnectedAccount($workspace);
        $post = $this->createPublishedPost($workspace, $account, 'ig_media_18001');

        $created = app(CommentSyncService::class)->syncWorkspace($workspace);

        $this->assertGreaterThanOrEqual(2, $created);
        $this->assertDatabaseHas('comments', [
            'workspace_id' => $workspace->id,
            'post_id' => $post->id,
            'platform' => 'instagram',
            'external_id' => 'ig_media_18001_comment_0',
            'sentiment' => 'neutral',
        ]);
    }

    public function test_second_sync_run_creates_no_duplicates(): void
    {
        config()->set('social_providers.drivers.instagram', 'fake');

        $user = User::factory()->create();
        $workspace = $this->setupWorkspace($user);
        $account = $this->createConnectedAccount($workspace);
        $this->createPublishedPost($workspace, $account, 'ig_media_18002');

        $service = app(CommentSyncService::class);

        $firstRun = $service->syncWorkspace($workspace);
        $countAfterFirst = Comment::count();

        $secondRun = $service->syncWorkspace($workspace);

        $this->assertGreaterThan(0, $firstRun);
        $this->assertSame(0, $secondRun);
        $this->assertSame($countAfterFirst, Comment::count());
    }

    public function test_provider_without_comments_capability_is_skipped(): void
    {
        config()->set('social_providers.drivers.facebook', 'fake');

        $user = User::factory()->create();
        $workspace = $this->setupWorkspace($user);
        $account = $this->createConnectedAccount($workspace, 'facebook');
        $this->createPublishedPost($workspace, $account, 'fb_post_900');

        $created = app(CommentSyncService::class)->syncWorkspace($workspace);

        $this->assertSame(0, $created);
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_account_with_api_error_is_skipped_without_failing(): void
    {
        config()->set('social_providers.drivers.instagram', 'fake');
        config()->set(
            'social_providers.implementations.fake.instagram',
            ThrowingCommentProvider::class,
        );

        $user = User::factory()->create();
        $workspace = $this->setupWorkspace($user);
        $account = $this->createConnectedAccount($workspace);
        $this->createPublishedPost($workspace, $account, 'ig_media_18003');

        $created = app(CommentSyncService::class)->syncWorkspace($workspace);

        $this->assertSame(0, $created);
        $this->assertDatabaseCount('comments', 0);
    }

    public function test_manual_comment_creation_still_works_with_unique_index(): void
    {
        $user = User::factory()->create();
        $workspace = $this->setupWorkspace($user);
        $headers = $this->authHeaders($user);

        foreach (['Erster Kommentar', 'Zweiter Kommentar'] as $text) {
            $this->withHeaders($headers)
                ->postJson('/api/v1/comments', [
                    'workspace_id' => $workspace->id,
                    'platform' => 'instagram',
                    'author' => 'manual_user',
                    'text' => $text,
                ])
                ->assertStatus(201);
        }

        // Two manual comments both have NULL external_id — the unique index
        // on (platform, external_id) must not reject them.
        $this->assertDatabaseCount('comments', 2);

        $this->withHeaders($headers)
            ->getJson("/api/v1/comments?workspace_id={$workspace->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.comments');
    }
}

class ThrowingCommentProvider extends BaseSocialProvider implements FetchesComments
{
    public function platform(): string
    {
        return 'instagram';
    }

    public function publish(Post $post): PublishResponseDTO
    {
        return $this->success();
    }

    public function fetchComments(
        SocialAccount $account,
        string $providerMediaId,
        ?string $since = null,
    ): CommentCollectionDTO {
        throw SocialProviderException::invalidAccount(
            'instagram',
            'Error validating access token: session has expired.',
        );
    }

    /**
     * @return list<string>
     */
    public static function capabilities(): array
    {
        return ['publish', 'fetch_comments'];
    }
}
