<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\WorkspaceRole;
use App\Jobs\PublishPostJob;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspace(User $user): Workspace
    {
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Test WS',
            'slug' => 'test-ws',
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner,
            'created_at' => now(),
        ]);

        $this->subscribeWorkspace($workspace);

        return $workspace;
    }

    private function authHeaders(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_user_can_create_and_schedule_post(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $workspace = $this->setupWorkspace($user);
        $headers = $this->authHeaders($user);

        $create = $this->withHeaders($headers)
            ->postJson('/api/v1/posts', [
                'workspace_id' => $workspace->id,
                'title' => 'Hello',
                'content' => 'World',
            ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.post.title', 'Hello');

        $postId = $create->json('data.post.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/posts/{$postId}/schedule?workspace_id={$workspace->id}", [
                'scheduled_at' => now()->addHour()->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('data.post.status', PostStatus::Scheduled->value);

        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'status' => PostStatus::Scheduled->value,
        ]);

        Queue::assertPushed(PublishPostJob::class);
    }

    public function test_schedule_rejects_past_date(): void
    {
        $user = User::factory()->create();
        $workspace = $this->setupWorkspace($user);
        $headers = $this->authHeaders($user);

        $create = $this->withHeaders($headers)
            ->postJson('/api/v1/posts', [
                'workspace_id' => $workspace->id,
                'title' => 'Past',
                'content' => 'Test',
            ]);

        $postId = $create->json('data.post.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/posts/{$postId}/schedule?workspace_id={$workspace->id}", [
                'scheduled_at' => now()->subHour()->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_at']);
    }
}
