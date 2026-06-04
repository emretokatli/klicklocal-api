<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permission;
use App\Support\PlatformRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_customer_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/admin/users')
            ->assertForbidden();
    }

    public function test_platform_admin_can_list_users(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(\App\Support\TeamContext::PLATFORM);

        $admin = User::factory()->create();
        $admin->assignRole(PlatformRole::SUPER_ADMIN);

        $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['users']]);
    }

    public function test_platform_admin_can_list_ai_prompts(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(\App\Support\TeamContext::PLATFORM);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName(PlatformRole::ADMIN, Permission::GUARD));

        $this->withHeaders($this->authHeaders($admin))
            ->getJson('/api/v1/admin/ai-prompts')
            ->assertOk();
    }
}
