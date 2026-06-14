<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\BusinessProfile;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\TrendSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('trends.driver', 'fake');
        config()->set('services.openai.driver', 'fake');
        config()->set('services.openai.key', '');

        $this->seed(TrendSeeder::class);
    }

    /**
     * @param  list<string>|null  $channels
     * @return array{0: User, 1: Workspace}
     */
    private function createUserWithWorkspace(
        string $businessType = 'Bäckerei',
        ?array $channels = null,
    ): array {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Plan WS',
            'slug' => 'plan-ws-'.uniqid(),
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner,
            'created_at' => now(),
        ]);
        BusinessProfile::create([
            'workspace_id' => $workspace->id,
            'business_name' => 'Bäckerei Klein',
            'business_type' => $businessType,
            'social_media_channels' => $channels,
        ]);

        return [$user, $workspace];
    }

    private function authHeaders(User $user, Workspace $workspace): array
    {
        $token = $user->createToken('test')->plainTextToken;

        return [
            'Authorization' => 'Bearer '.$token,
            'X-Workspace-Id' => (string) $workspace->id,
        ];
    }

    public function test_weekly_plan_requires_subscription(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();

        $this->withHeaders($this->authHeaders($user, $workspace))
            ->getJson('/api/v1/content-plan/weekly')
            ->assertStatus(402);
    }

    public function test_subscribed_user_gets_weekly_plan(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        $this->subscribeWorkspace($workspace);

        $response = $this->withHeaders($this->authHeaders($user, $workspace))
            ->getJson('/api/v1/content-plan/weekly')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'week_start',
                    'suggestions' => [
                        ['day', 'date', 'category', 'category_label', 'platform', 'idea', 'trend_title'],
                    ],
                ],
            ]);

        $suggestions = $response->json('data.suggestions');
        $this->assertCount(4, $suggestions);

        // Fixed category rotation, all German ideas present.
        $categories = array_column($suggestions, 'category');
        $this->assertSame(['angebot', 'behind_the_scenes', 'trend', 'lokal'], $categories);

        foreach ($suggestions as $s) {
            $this->assertNotEmpty($s['idea']);
            $this->assertNotEmpty($s['day']);
            $this->assertContains($s['platform'], ['instagram', 'tiktok', 'facebook', 'linkedin']);
        }

        // The Trend slot is anchored to a real fitting trend.
        $trendSlot = collect($suggestions)->firstWhere('category', 'trend');
        $this->assertNotNull($trendSlot['trend_title']);
    }

    public function test_plan_is_deterministic_under_fake_driver(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        $this->subscribeWorkspace($workspace);

        $headers = $this->authHeaders($user, $workspace);

        $first = $this->withHeaders($headers)
            ->getJson('/api/v1/content-plan/weekly')->json('data');
        $second = $this->withHeaders($headers)
            ->getJson('/api/v1/content-plan/weekly')->json('data');

        $this->assertSame($first, $second);
    }

    public function test_platforms_come_from_business_profile_channels(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace(
            channels: ['Instagram', 'TikTok'],
        );
        $this->subscribeWorkspace($workspace);

        $suggestions = $this->withHeaders($this->authHeaders($user, $workspace))
            ->getJson('/api/v1/content-plan/weekly')
            ->assertOk()
            ->json('data.suggestions');

        $platforms = array_values(array_unique(array_column($suggestions, 'platform')));
        sort($platforms);

        // Both configured channels are used in the rotation.
        $this->assertSame(['instagram', 'tiktok'], $platforms);
    }
}
