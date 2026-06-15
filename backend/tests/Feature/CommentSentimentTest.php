<?php

namespace Tests\Feature;

use App\Enums\PostPlatformStatus;
use App\Enums\PostStatus;
use App\Enums\SocialAccountStatus;
use App\Enums\WorkspaceRole;
use App\Jobs\ClassifyCommentsJob;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Ai\Contracts\OpenAiClientInterface;
use App\Services\Ai\DTOs\GeneratedContentDTO;
use App\Services\Ai\DTOs\GeneratedImageDTO;
use App\Services\Ai\DTOs\SentimentBatchDTO;
use App\Services\Ai\DTOs\SuggestedReplyDTO;
use App\Services\Comments\CommentSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentSentimentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.driver', 'fake');
    }

    private function setupWorkspace(): Workspace
    {
        $user = User::factory()->create();

        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Test WS',
            'slug' => 'test-ws-'.uniqid(),
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner,
            'created_at' => now(),
        ]);

        return $workspace;
    }

    private function createComment(Workspace $workspace, string $text, array $overrides = []): Comment
    {
        return Comment::create(array_merge([
            'workspace_id' => $workspace->id,
            'platform' => 'instagram',
            'author' => 'test_user',
            'text' => $text,
            'commented_at' => now(),
        ], $overrides));
    }

    public function test_batch_classification_sets_sentiment_and_timestamp(): void
    {
        $workspace = $this->setupWorkspace();

        $positive = $this->createComment($workspace, 'Super Service, vielen Dank!');
        $negative = $this->createComment($workspace, 'Leider sehr schlecht, bin enttäuscht.');
        $neutral = $this->createComment($workspace, 'Habt ihr auch samstags geöffnet?');

        ClassifyCommentsJob::dispatchSync($workspace);

        $this->assertSame('positive', $positive->fresh()->sentiment);
        $this->assertSame('negative', $negative->fresh()->sentiment);
        $this->assertSame('neutral', $neutral->fresh()->sentiment);

        foreach ([$positive, $negative, $neutral] as $comment) {
            $this->assertNotNull($comment->fresh()->sentiment_classified_at);
        }
    }

    public function test_keyword_cases_map_to_expected_classes(): void
    {
        $workspace = $this->setupWorkspace();

        $expectations = [
            'Tolle Idee, weiter so! 👏' => 'positive',
            'Danke für den schnellen Service' => 'positive',
            'Einfach klasse hier ❤' => 'positive',
            'Nie wieder, ganz schlecht!' => 'negative',
            'Preise sind gestiegen, schade.' => 'negative',
            'Gibt es das auch vegan?' => 'neutral',
        ];

        $comments = [];

        foreach ($expectations as $text => $expected) {
            $comments[$text] = $this->createComment($workspace, $text);
        }

        ClassifyCommentsJob::dispatchSync($workspace);

        foreach ($expectations as $text => $expected) {
            $this->assertSame($expected, $comments[$text]->fresh()->sentiment, "Text: {$text}");
        }
    }

    public function test_invalid_model_output_leaves_rows_unclassified_without_failing(): void
    {
        $workspace = $this->setupWorkspace();
        $comment = $this->createComment($workspace, 'Super Laden!');

        $this->app->bind(OpenAiClientInterface::class, fn () => new GarbageSentimentClient([
            'not even an array',
            ['id' => 999999999, 'sentiment' => 'positive'],   // id not in batch
            ['id' => $comment->id, 'sentiment' => 'angry'],   // out-of-enum
            ['id' => 'abc', 'sentiment' => 'positive'],       // non-numeric id
            ['sentiment' => 'positive'],                      // missing id
        ]));

        ClassifyCommentsJob::dispatchSync($workspace);

        $fresh = $comment->fresh();
        $this->assertSame('neutral', $fresh->sentiment);
        $this->assertNull($fresh->sentiment_classified_at);
    }

    public function test_already_classified_rows_are_untouched_on_rerun(): void
    {
        $workspace = $this->setupWorkspace();

        // Manually classified as negative although the text would heuristically
        // be positive — a re-run must not overwrite it.
        $classifiedAt = now()->subDay();
        $manual = $this->createComment($workspace, 'Super, danke!', [
            'sentiment' => 'negative',
            'sentiment_classified_at' => $classifiedAt,
        ]);

        ClassifyCommentsJob::dispatchSync($workspace);

        $fresh = $manual->fresh();
        $this->assertSame('negative', $fresh->sentiment);
        $this->assertSame(
            $classifiedAt->toDateTimeString(),
            $fresh->sentiment_classified_at->toDateTimeString(),
        );
    }

    public function test_per_run_cap_is_respected(): void
    {
        config()->set('comments.classification.max_per_run', 2);

        $workspace = $this->setupWorkspace();

        for ($i = 0; $i < 5; $i++) {
            $this->createComment($workspace, "Super Kommentar {$i}");
        }

        ClassifyCommentsJob::dispatchSync($workspace);

        $this->assertSame(2, Comment::whereNotNull('sentiment_classified_at')->count());
        $this->assertSame(3, Comment::whereNull('sentiment_classified_at')->count());
    }

    public function test_explicit_limit_is_capped_by_config(): void
    {
        config()->set('comments.classification.max_per_run', 3);

        $workspace = $this->setupWorkspace();

        for ($i = 0; $i < 5; $i++) {
            $this->createComment($workspace, "Kommentar {$i}");
        }

        ClassifyCommentsJob::dispatchSync($workspace, null, 100);

        $this->assertSame(3, Comment::whereNotNull('sentiment_classified_at')->count());
    }

    public function test_full_pipeline_sync_then_automatic_classification(): void
    {
        config()->set('social_providers.drivers.instagram', 'fake');

        $workspace = $this->setupWorkspace();

        $account = SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => 'instagram',
            'provider_account_id' => '1789'.$workspace->id,
            'account_name' => 'Test Account',
            'username' => 'test_account',
            'status' => SocialAccountStatus::Connected,
            'access_token' => 'fake-token',
        ]);

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
            'platform_post_id' => 'ig_media_18001',
            'published_at' => now()->subDay(),
            'created_at' => now()->subDay(),
        ]);

        // Sync fires CommentsIngested → ClassifyIngestedComments listener →
        // ClassifyCommentsJob (sync queue) → fake driver classifies.
        $created = app(CommentSyncService::class)->syncWorkspace($workspace);

        $this->assertGreaterThan(0, $created);
        $this->assertSame(0, Comment::whereNull('sentiment_classified_at')->count());
        $this->assertSame($created, Comment::whereNotNull('sentiment_classified_at')->count());
    }
}

class GarbageSentimentClient implements OpenAiClientInterface
{
    public function __construct(private readonly array $results) {}

    public function generateContent(
        string $systemPrompt,
        string $userPrompt,
        ?string $imageUrl,
        array $context = [],
    ): GeneratedContentDTO {
        throw new \RuntimeException('Not used in this test.');
    }

    public function generateImage(
        string $prompt,
        array $context = [],
        string $size = '1024x1024',
        string $quality = 'standard',
    ): GeneratedImageDTO {
        throw new \RuntimeException('Not used in this test.');
    }

    public function classifySentiments(string $systemPrompt, array $comments): SentimentBatchDTO
    {
        return new SentimentBatchDTO(
            results: $this->results,
            model: 'garbage-model',
            tokensUsed: 0,
        );
    }

    public function suggestCommentReply(
        string $systemPrompt,
        string $userPrompt,
        array $context = [],
    ): SuggestedReplyDTO {
        throw new \RuntimeException('Not used in this test.');
    }

    public function commentOnTrends(string $systemPrompt, array $trends, array $context = []): array
    {
        throw new \RuntimeException('Not used in this test.');
    }

    public function suggestContentPlan(
        string $systemPrompt,
        array $analytics,
        array $context = [],
    ): \App\Services\Ai\DTOs\ContentPlanSuggestionDTO {
        throw new \RuntimeException('Not used in this test.');
    }
}
