<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\BusinessProfile;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Trends\TrendIndustryMatcher;
use Database\Seeders\TrendSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendApiTest extends TestCase
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
     * @return array{0: User, 1: Workspace}
     */
    private function createUserWithWorkspace(?string $businessType = 'Bäckerei'): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Trend WS',
            'slug' => 'trend-ws-'.uniqid(),
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner,
            'created_at' => now(),
        ]);

        if ($businessType !== null) {
            BusinessProfile::create([
                'workspace_id' => $workspace->id,
                'business_name' => 'Trend Test',
                'business_type' => $businessType,
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

    public function test_trends_endpoint_returns_annotated_trends_without_subscription(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace('Bäckerei');

        $response = $this->withHeaders($this->authHeaders($user, $workspace))
            ->getJson('/api/v1/trends')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.business_type', 'Bäckerei')
            ->assertJsonStructure([
                'data' => [
                    'business_type',
                    'trends' => [
                        ['id', 'title', 'category', 'score', 'fit', 'comment', 'suggestion'],
                    ],
                ],
            ]);

        $trends = $response->json('data.trends');
        $this->assertNotEmpty($trends);

        // Every trend carries a German AI comment and a suggestion badge.
        foreach ($trends as $trend) {
            $this->assertNotEmpty($trend['comment']);
            $this->assertNotEmpty($trend['suggestion']);
        }

        // Fitting trends are sorted first.
        $fitFlags = array_column($trends, 'fit');
        $sorted = $fitFlags;
        rsort($sorted);
        $this->assertSame($sorted, $fitFlags);
    }

    public function test_bakery_fits_food_trends_but_lawyer_does_not(): void
    {
        $matcher = app(TrendIndustryMatcher::class);

        $provider = app(\App\Services\Trends\Factory\TrendProviderFactory::class)->make();
        $topics = $provider->topics();

        $bakery = collect($matcher->match('Bäckerei', $topics));
        $lawyer = collect($matcher->match('Anwalt', $topics));

        $gastroBakery = $bakery->firstWhere('category', 'gastronomie');
        $gastroLawyer = $lawyer->firstWhere('category', 'gastronomie');

        $this->assertNotNull($gastroBakery);
        $this->assertTrue($gastroBakery->fit, 'A bakery should fit gastronomie trends.');

        $this->assertNotNull($gastroLawyer);
        $this->assertFalse($gastroLawyer->fit, 'A lawyer should not fit gastronomie trends.');
    }

    public function test_category_mapping(): void
    {
        $matcher = app(TrendIndustryMatcher::class);

        $this->assertSame('gastronomie', $matcher->categoryFor('Bäckerei'));
        $this->assertSame('beauty', $matcher->categoryFor('Friseur Salon'));
        $this->assertSame('handwerk', $matcher->categoryFor('Tischlerei Müller'));
        $this->assertSame('dienstleistung', $matcher->categoryFor('Anwalt'));
        // Unknown but non-empty type falls back to the general category.
        $this->assertSame('dienstleistung', $matcher->categoryFor('Raumfahrtagentur'));
        // Empty type maps to nothing (no trend fits).
        $this->assertNull($matcher->categoryFor(null));
        $this->assertNull($matcher->categoryFor('  '));
    }

    public function test_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/trends')->assertUnauthorized();
    }
}
