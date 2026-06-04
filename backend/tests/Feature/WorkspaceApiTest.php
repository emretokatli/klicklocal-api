<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_user_can_create_and_list_workspaces(): void
    {
        $user = User::factory()->create();

        $create = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/v1/workspaces', [
                'name' => 'My Brand',
                'timezone' => 'America/New_York',
            ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.workspace.name', 'My Brand')
            ->assertJsonPath('data.workspace.slug', 'my-brand');

        $list = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/workspaces');

        $list->assertOk()
            ->assertJsonCount(1, 'data.workspaces');
    }

    public function test_user_can_update_and_delete_workspace(): void
    {
        $user = User::factory()->create();

        $create = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/v1/workspaces', ['name' => 'Original']);

        $id = $create->json('data.workspace.id');

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/v1/workspaces/{$id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.workspace.name', 'Renamed');

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/v1/workspaces/{$id}")
            ->assertOk();

        $this->assertDatabaseMissing('workspaces', ['id' => $id]);
    }
}
