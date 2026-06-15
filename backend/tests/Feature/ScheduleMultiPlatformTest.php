<?php

namespace Tests\Feature;

use App\Enums\BillingProvider;
use App\Enums\PlanFeature;
use App\Enums\PostStatus;
use App\Enums\SocialAccountStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WorkspaceRole;
use App\Jobs\PublishPostJob;
use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\PlanFeatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduleMultiPlatformTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Sched WS',
            'slug' => 'sched-ws-'.uniqid(),
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner,
            'created_at' => now(),
        ]);

        return [$user, $workspace];
    }

    private function subscribe(Workspace $workspace, string $scheduledLimit = '50'): void
    {
        $plan = Plan::create([
            'slug' => 'sched-plan-'.uniqid(),
            'name' => 'Sched Plan',
            'monthly_price' => 9.99,
            'yearly_price' => 99.00,
            'trial_days' => 0,
            'sort_order' => 96,
            'is_active' => true,
        ]);

        app(PlanFeatureService::class)->sync($plan, [
            PlanFeature::ScheduledPostsMonthly->value => $scheduledLimit,
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

    private function account(Workspace $workspace, string $provider): SocialAccount
    {
        return SocialAccount::create([
            'workspace_id' => $workspace->id,
            'provider' => $provider,
            'provider_account_id' => $provider.'-'.uniqid(),
            'username' => $provider,
            'access_token' => 'token',
            'status' => SocialAccountStatus::Connected,
        ]);
    }

    private function headers(User $user, Workspace $workspace): array
    {
        return [
            'Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken,
            'X-Workspace-Id' => (string) $workspace->id,
        ];
    }

    public function test_schedules_same_content_to_multiple_platforms(): void
    {
        Queue::fake();

        [$user, $workspace] = $this->userWithWorkspace();
        $this->subscribe($workspace);
        $ig = $this->account($workspace, 'instagram');
        $tt = $this->account($workspace, 'tiktok');

        $response = $this->withHeaders($this->headers($user, $workspace))
            ->postJson('/api/v1/posts/schedule', [
                'content' => 'Gleicher Beitrag auf mehreren Kanälen',
                'scheduled_at' => now()->addHours(2)->toIso8601String(),
                'social_account_ids' => [$ig->id, $tt->id],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.post.status', PostStatus::Scheduled->value);

        $postId = $response->json('data.post.id');

        $this->assertDatabaseHas('post_platforms', [
            'post_id' => $postId,
            'social_account_id' => $ig->id,
        ]);
        $this->assertDatabaseHas('post_platforms', [
            'post_id' => $postId,
            'social_account_id' => $tt->id,
        ]);

        Queue::assertPushed(PublishPostJob::class);
    }

    public function test_schedule_requires_future_date(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $this->subscribe($workspace);
        $ig = $this->account($workspace, 'instagram');

        $this->withHeaders($this->headers($user, $workspace))
            ->postJson('/api/v1/posts/schedule', [
                'content' => 'Vergangenheit',
                'scheduled_at' => now()->subHour()->toIso8601String(),
                'social_account_ids' => [$ig->id],
            ])
            ->assertStatus(422);
    }

    public function test_schedule_requires_subscription(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $ig = $this->account($workspace, 'instagram');

        // No subscription → subscription.required gate returns 402.
        $this->withHeaders($this->headers($user, $workspace))
            ->postJson('/api/v1/posts/schedule', [
                'content' => 'Ohne Abo',
                'scheduled_at' => now()->addHour()->toIso8601String(),
                'social_account_ids' => [$ig->id],
            ])
            ->assertStatus(402);
    }
}
