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

    public function test_super_admin_can_assign_platform_role(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(\App\Support\TeamContext::PLATFORM);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(PlatformRole::SUPER_ADMIN);

        $customer = User::factory()->create();

        $this->withHeaders($this->authHeaders($superAdmin))
            ->putJson("/api/v1/admin/users/{$customer->id}/roles", [
                'roles' => ['admin'],
            ])
            ->assertOk()
            ->assertJsonPath('data.user.platform_roles', ['admin']);
    }

    public function test_super_admin_can_revoke_platform_roles_with_empty_array(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(\App\Support\TeamContext::PLATFORM);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(PlatformRole::SUPER_ADMIN);

        $customer = User::factory()->create();
        $customer->assignRole(PlatformRole::SUPPORT);

        $this->withHeaders($this->authHeaders($superAdmin))
            ->putJson("/api/v1/admin/users/{$customer->id}/roles", [
                'roles' => [],
            ])
            ->assertOk()
            ->assertJsonPath('data.user.platform_roles', []);
    }

    public function test_super_admin_cannot_assign_workspace_role_as_platform_role(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(\App\Support\TeamContext::PLATFORM);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(PlatformRole::SUPER_ADMIN);

        $customer = User::factory()->create();

        $this->withHeaders($this->authHeaders($superAdmin))
            ->putJson("/api/v1/admin/users/{$customer->id}/roles", [
                'roles' => ['owner'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['roles.0']);
    }

    public function test_non_super_admin_cannot_update_platform_roles(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(\App\Support\TeamContext::PLATFORM);

        $admin = User::factory()->create();
        $admin->assignRole(PlatformRole::ADMIN);

        $customer = User::factory()->create();

        $this->withHeaders($this->authHeaders($admin))
            ->putJson("/api/v1/admin/users/{$customer->id}/roles", [
                'roles' => ['support'],
            ])
            ->assertForbidden();
    }

    public function test_registered_customer_is_not_platform_admin(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(\App\Support\TeamContext::PLATFORM);

        $customer = User::factory()->create();

        $this->withHeaders($this->authHeaders($customer))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.is_platform_admin', false)
            ->assertJsonPath('data.abilities.platform_roles', []);
    }
}
