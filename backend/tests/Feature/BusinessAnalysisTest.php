<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\BusinessProfile;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Force the deterministic fake analyzer.
        config()->set('services.openai.driver', 'fake');
        config()->set('services.openai.key', '');
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function createUserWithWorkspace(?string $website = 'https://example-cafe.de'): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Café Test',
            'slug' => 'cafe-test-'.uniqid(),
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner,
            'created_at' => now(),
        ]);

        if ($website !== null) {
            BusinessProfile::create([
                'workspace_id' => $workspace->id,
                'business_name' => 'Café Test',
                'business_type' => 'Café',
                'website' => $website,
            ]);
        }

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

    public function test_unsubscribed_user_gets_teaser_tier_without_full_data(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();

        $response = $this->withHeaders($this->authHeaders($user, $workspace))
            ->getJson('/api/v1/business-profile/analysis')
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.tier', 'teaser')
            ->assertJsonStructure([
                'data' => [
                    'tier',
                    'available',
                    'analysis' => [
                        'score',
                        'band',
                        'summary',
                        'brand_tone',
                        'strengths_count',
                        'weaknesses_count',
                        'locked_sections',
                    ],
                ],
            ]);

        $analysis = $response->json('data.analysis');

        // Counts are present...
        $this->assertIsInt($analysis['strengths_count']);
        $this->assertGreaterThan(0, $analysis['strengths_count']);

        // ...but the full lists / detail are NOT serialized for teaser clients.
        $this->assertArrayNotHasKey('strengths', $analysis);
        $this->assertArrayNotHasKey('weaknesses', $analysis);
        $this->assertArrayNotHasKey('seo_assessment', $analysis);
        $this->assertArrayNotHasKey('growth_note', $analysis);
        $this->assertArrayNotHasKey('services', $analysis);
        $this->assertArrayNotHasKey('target_audience', $analysis);
    }

    public function test_subscribed_user_gets_full_tier(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        $this->subscribeWorkspace($workspace);

        $response = $this->withHeaders($this->authHeaders($user, $workspace))
            ->getJson('/api/v1/business-profile/analysis')
            ->assertOk()
            ->assertJsonPath('data.tier', 'full')
            ->assertJsonStructure([
                'data' => [
                    'analysis' => [
                        'score',
                        'band',
                        'summary',
                        'services',
                        'seo_assessment',
                        'strengths',
                        'weaknesses',
                        'brand_tone',
                        'target_audience',
                        'growth_note',
                    ],
                ],
            ]);

        $analysis = $response->json('data.analysis');
        $this->assertIsArray($analysis['strengths']);
        $this->assertNotEmpty($analysis['strengths']);
        $this->assertContains($analysis['band'], ['Kritisch', 'Ausbaufähig', 'Solide', 'Stark']);
    }

    public function test_endpoint_does_not_require_subscription(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();

        // Unsubscribed → must be 200 (teaser), never 402.
        $this->withHeaders($this->authHeaders($user, $workspace))
            ->getJson('/api/v1/business-profile/analysis')
            ->assertOk();
    }

    public function test_analysis_is_persisted_and_not_recomputed(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();

        $this->withHeaders($this->authHeaders($user, $workspace))
            ->getJson('/api/v1/business-profile/analysis')
            ->assertOk();

        $profile = BusinessProfile::where('workspace_id', $workspace->id)->firstOrFail();
        $this->assertNotNull($profile->website_analysis);
        $this->assertNotNull($profile->website_analyzed_at);
        $this->assertSame($profile->website, $profile->website_analysis_url);

        $firstTimestamp = $profile->website_analyzed_at->toIso8601String();

        // Second call must reuse the cached analysis (timestamp unchanged).
        $this->travel(2)->minutes();

        $this->withHeaders($this->authHeaders($user, $workspace))
            ->getJson('/api/v1/business-profile/analysis')
            ->assertOk()
            ->assertJsonPath('data.analyzed_at', $firstTimestamp);
    }

    public function test_changing_website_triggers_recompute(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();

        $this->withHeaders($this->authHeaders($user, $workspace))
            ->getJson('/api/v1/business-profile/analysis')
            ->assertOk();

        $profile = BusinessProfile::where('workspace_id', $workspace->id)->firstOrFail();
        $firstTimestamp = $profile->website_analyzed_at->toIso8601String();

        $this->travel(2)->minutes();
        $profile->update(['website' => 'https://changed-domain.de']);

        $this->withHeaders($this->authHeaders($user, $workspace))
            ->getJson('/api/v1/business-profile/analysis')
            ->assertOk();

        $profile->refresh();
        $this->assertSame('https://changed-domain.de', $profile->website_analysis_url);
        $this->assertNotSame($firstTimestamp, $profile->website_analyzed_at->toIso8601String());
    }

    public function test_no_website_returns_unavailable(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace(website: null);

        $this->withHeaders($this->authHeaders($user, $workspace))
            ->getJson('/api/v1/business-profile/analysis')
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.analysis', null);
    }
}
