<?php

namespace Tests\Feature;

use App\Enums\BillingProvider;
use App\Enums\PlanFeature;
use App\Enums\SubscriptionStatus;
use App\Enums\WorkspaceRole;
use App\Models\Comment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\PlanFeatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentReplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.driver', 'fake');
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function createUserWithWorkspace(): array
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

        return [$user, $workspace];
    }

    private function subscribe(Workspace $workspace): void
    {
        $plan = Plan::create([
            'slug' => 'ai-plan-'.uniqid(),
            'name' => 'AI Plan',
            'monthly_price' => 9.99,
            'yearly_price' => 99.00,
            'trial_days' => 0,
            'sort_order' => 98,
            'is_active' => true,
        ]);

        app(PlanFeatureService::class)->sync($plan, [
            PlanFeature::AiGeneration->value => '1',
        ]);

        Subscription::create([
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'provider' => BillingProvider::Manual,
            'status' => SubscriptionStatus::Active,
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    private function authHeaders(User $user, Workspace $workspace): array
    {
        $token = $user->createToken('test')->plainTextToken;

        return [
            'Authorization' => 'Bearer '.$token,
            'X-Workspace-Id' => (string) $workspace->id,
        ];
    }

    private function createComment(Workspace $workspace, array $overrides = []): Comment
    {
        return Comment::create(array_merge([
            'workspace_id' => $workspace->id,
            'platform' => 'instagram',
            'external_id' => 'ig_c_'.uniqid(),
            'author' => 'test_user',
            'text' => 'Super Service, vielen Dank!',
            'sentiment' => 'positive',
            'sentiment_classified_at' => now(),
            'commented_at' => now(),
        ], $overrides));
    }

    public function test_stats_counts_per_sentiment_and_respects_platform_filter(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();

        $this->createComment($workspace, ['sentiment' => 'positive']);
        $this->createComment($workspace, ['sentiment' => 'positive']);
        $this->createComment($workspace, ['sentiment' => 'negative']);
        $this->createComment($workspace, ['sentiment' => 'neutral', 'platform' => 'tiktok']);

        $headers = $this->authHeaders($user, $workspace);

        $this->withHeaders($headers)
            ->getJson('/api/v1/comments/stats?workspace_id='.$workspace->id)
            ->assertOk()
            ->assertJsonPath('data.stats.total', 4)
            ->assertJsonPath('data.stats.positive', 2)
            ->assertJsonPath('data.stats.neutral', 1)
            ->assertJsonPath('data.stats.negative', 1);

        $this->withHeaders($headers)
            ->getJson('/api/v1/comments/stats?workspace_id='.$workspace->id.'&platform=tiktok')
            ->assertOk()
            ->assertJsonPath('data.stats.total', 1)
            ->assertJsonPath('data.stats.neutral', 1)
            ->assertJsonPath('data.stats.positive', 0);
    }

    public function test_stats_ignores_other_workspaces(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        [, $other] = $this->createUserWithWorkspace();

        $this->createComment($other);

        $this->withHeaders($this->authHeaders($user, $workspace))
            ->getJson('/api/v1/comments/stats?workspace_id='.$workspace->id)
            ->assertOk()
            ->assertJsonPath('data.stats.total', 0);
    }

    public function test_suggest_reply_stores_suggestion_and_records_usage(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        $this->subscribe($workspace);

        $comment = $this->createComment($workspace);

        $response = $this->withHeaders($this->authHeaders($user, $workspace))
            ->postJson("/api/v1/comments/{$comment->id}/suggest-reply")
            ->assertOk();

        $suggestion = $response->json('data.comment.suggested_reply');
        $this->assertIsString($suggestion);
        $this->assertNotSame('', $suggestion);
        $this->assertSame($suggestion, $comment->fresh()->suggested_reply);

        $this->assertDatabaseHas('usage_records', [
            'workspace_id' => $workspace->id,
            'metric' => 'comment_reply_suggestion',
        ]);
    }

    public function test_suggest_reply_without_subscription_returns_402(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        $comment = $this->createComment($workspace);

        $this->withHeaders($this->authHeaders($user, $workspace))
            ->postJson("/api/v1/comments/{$comment->id}/suggest-reply")
            ->assertStatus(402);
    }

    public function test_suggest_reply_for_foreign_comment_returns_404(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        [, $other] = $this->createUserWithWorkspace();
        $this->subscribe($workspace);

        $comment = $this->createComment($other);

        $this->withHeaders($this->authHeaders($user, $workspace))
            ->postJson("/api/v1/comments/{$comment->id}/suggest-reply")
            ->assertNotFound();
    }

    public function test_reply_stores_text_and_timestamp(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        $comment = $this->createComment($workspace);

        $this->withHeaders($this->authHeaders($user, $workspace))
            ->postJson("/api/v1/comments/{$comment->id}/reply", [
                'reply_text' => 'Danke dir! Bis bald.',
            ])
            ->assertOk()
            ->assertJsonPath('data.comment.reply_text', 'Danke dir! Bis bald.');

        $fresh = $comment->fresh();
        $this->assertSame('Danke dir! Bis bald.', $fresh->reply_text);
        $this->assertNotNull($fresh->replied_at);
    }

    public function test_reply_does_not_require_subscription(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        // no subscription on purpose
        $comment = $this->createComment($workspace);

        $this->withHeaders($this->authHeaders($user, $workspace))
            ->postJson("/api/v1/comments/{$comment->id}/reply", [
                'reply_text' => 'Danke!',
            ])
            ->assertOk();
    }

    public function test_reply_to_manual_comment_without_external_id_returns_422(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        $comment = $this->createComment($workspace, ['external_id' => null]);

        $this->withHeaders($this->authHeaders($user, $workspace))
            ->postJson("/api/v1/comments/{$comment->id}/reply", [
                'reply_text' => 'Danke!',
            ])
            ->assertStatus(422);

        $this->assertNull($comment->fresh()->replied_at);
    }

    public function test_second_reply_returns_422(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        $comment = $this->createComment($workspace, [
            'reply_text' => 'Erste Antwort.',
            'replied_at' => now()->subHour(),
        ]);

        $this->withHeaders($this->authHeaders($user, $workspace))
            ->postJson("/api/v1/comments/{$comment->id}/reply", [
                'reply_text' => 'Zweite Antwort.',
            ])
            ->assertStatus(422);

        $this->assertSame('Erste Antwort.', $comment->fresh()->reply_text);
    }

    public function test_reply_for_foreign_comment_returns_404(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        [, $other] = $this->createUserWithWorkspace();

        $comment = $this->createComment($other);

        $this->withHeaders($this->authHeaders($user, $workspace))
            ->postJson("/api/v1/comments/{$comment->id}/reply", [
                'reply_text' => 'Hallo!',
            ])
            ->assertNotFound();
    }
}
