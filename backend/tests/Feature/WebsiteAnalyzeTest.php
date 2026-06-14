<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsiteAnalyzeRun;
use App\Support\PlatformRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebsiteAnalyzeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('webanalyze.driver', 'fake');
        config()->set('queue.default', 'sync');
    }

    private function authHeaders(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_customer_cannot_analyze_website_in_admin(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/v1/admin/website-analyze', [
                'website' => 'https://example.de',
            ])
            ->assertForbidden();
    }

    public function test_platform_admin_can_analyze_website_with_fake_driver(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)
            ->setPermissionsTeamId(\App\Support\TeamContext::PLATFORM);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName(PlatformRole::ADMIN, \App\Support\Permission::GUARD));

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/api/v1/admin/website-analyze', [
                'website' => 'example-gastro.de',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.run.status', WebsiteAnalyzeRun::STATUS_COMPLETED)
            ->assertJsonPath('data.run.result.score', 52)
            ->assertJsonPath('data.run.result.band', 'Ausbaufähig')
            ->assertJsonStructure([
                'data' => [
                    'run' => [
                        'id',
                        'website',
                        'status',
                        'partial',
                        'error_message',
                        'result' => [
                            'website',
                            'score',
                            'band',
                            'report_markdown',
                        ],
                    ],
                ],
            ]);
    }

    public function test_platform_admin_can_poll_website_analyze_run(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)
            ->setPermissionsTeamId(\App\Support\TeamContext::PLATFORM);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName(PlatformRole::ADMIN, \App\Support\Permission::GUARD));

        $run = WebsiteAnalyzeRun::query()->create([
            'user_id' => $admin->id,
            'website' => 'https://example.de',
            'status' => WebsiteAnalyzeRun::STATUS_COMPLETED,
            'result' => [
                'website' => 'https://example.de',
                'score' => 40,
                'band' => 'Ausbaufähig',
                'report_markdown' => '# Test',
                'errors' => [],
            ],
            'completed_at' => now(),
        ]);

        $this->withHeaders($this->authHeaders($admin))
            ->getJson("/api/v1/admin/website-analyze/{$run->id}")
            ->assertOk()
            ->assertJsonPath('data.run.id', $run->id)
            ->assertJsonPath('data.run.result.score', 40);
    }

    public function test_platform_admin_can_list_website_analyze_runs(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)
            ->setPermissionsTeamId(\App\Support\TeamContext::PLATFORM);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName(PlatformRole::ADMIN, \App\Support\Permission::GUARD));

        WebsiteAnalyzeRun::query()->create([
            'user_id' => $admin->id,
            'website' => 'https://older.de',
            'status' => WebsiteAnalyzeRun::STATUS_COMPLETED,
            'result' => [
                'website' => 'https://older.de',
                'score' => 55,
                'band' => 'Ausbaufähig',
                'report_markdown' => '# Lead-Analyse',
                'errors' => [],
            ],
            'completed_at' => now()->subDay(),
        ]);

        WebsiteAnalyzeRun::query()->create([
            'user_id' => $admin->id,
            'website' => 'https://newer.de',
            'status' => WebsiteAnalyzeRun::STATUS_COMPLETED,
            'result' => [
                'website' => 'https://newer.de',
                'score' => 70,
                'band' => 'Solide',
                'report_markdown' => '# Lead-Analyse',
                'errors' => [],
            ],
            'completed_at' => now(),
        ]);

        $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/admin/website-analyze')
            ->assertOk()
            ->assertJsonPath('data.runs.0.website', 'https://newer.de')
            ->assertJsonPath('data.runs.0.score', 70)
            ->assertJsonPath('data.runs.1.website', 'https://older.de')
            ->assertJsonMissingPath('data.runs.0.result.report_markdown');
    }

    public function test_admin_cannot_view_another_users_website_analyze_run(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)
            ->setPermissionsTeamId(\App\Support\TeamContext::PLATFORM);

        $owner = User::factory()->create();
        $owner->assignRole(Role::findByName(PlatformRole::ADMIN, \App\Support\Permission::GUARD));

        $other = User::factory()->create();
        $other->assignRole(Role::findByName(PlatformRole::ADMIN, \App\Support\Permission::GUARD));

        $run = WebsiteAnalyzeRun::query()->create([
            'user_id' => $owner->id,
            'website' => 'https://private.de',
            'status' => WebsiteAnalyzeRun::STATUS_COMPLETED,
            'result' => ['report_markdown' => '# Test'],
            'completed_at' => now(),
        ]);

        $this->withHeaders($this->authHeaders($other))
            ->getJson("/api/v1/admin/website-analyze/{$run->id}")
            ->assertForbidden();
    }

    public function test_website_field_is_required(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)
            ->setPermissionsTeamId(\App\Support\TeamContext::PLATFORM);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName(PlatformRole::SUPER_ADMIN, \App\Support\Permission::GUARD));

        $this->withHeaders($this->authHeaders($admin))
            ->postJson('/api/v1/admin/website-analyze', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['website']);
    }
}
