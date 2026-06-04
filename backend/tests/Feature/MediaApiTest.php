<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_media(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Media WS',
            'slug' => 'media-ws',
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner,
            'created_at' => now(),
        ]);

        $this->subscribeWorkspace($workspace);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/media/upload', [
                'workspace_id' => $workspace->id,
                'file' => UploadedFile::fake()->image('photo.jpg'),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['media', 'url'],
            ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/media?workspace_id='.$workspace->id)
            ->assertOk()
            ->assertJsonPath('data.items.0.media.file_name', 'photo.jpg')
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        ['media', 'url'],
                    ],
                ],
            ]);
    }
}
