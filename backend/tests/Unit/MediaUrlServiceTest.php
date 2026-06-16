<?php

namespace Tests\Unit;

use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Media\Exceptions\MediaNotAccessibleException;
use App\Services\Media\MediaUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MediaUrlServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeMedia(string $mime = 'image/jpeg', string $name = 'photo.jpg'): Media
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'M',
            'slug' => 'm-'.uniqid(),
        ]);

        return Media::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'file_name' => $name,
            'file_path' => 'media/'.$workspace->id.'/'.$name,
            'file_type' => $mime,
            'file_size' => 1024,
            'mime_type' => $mime,
            'created_at' => now(),
        ]);
    }

    public function test_builds_absolute_public_url_from_app_url(): void
    {
        config([
            'app.url' => 'https://api.example.com',
            'media.public_base_url' => null,
            'media.signed_url_ttl_minutes' => 0,
        ]);

        $media = $this->makeMedia();

        $url = app(MediaUrlService::class)->publicUrl($media);

        $this->assertSame(
            'https://api.example.com/storage/'.$media->file_path,
            $url,
        );
    }

    public function test_respects_configured_public_base_url(): void
    {
        config([
            'app.url' => 'https://api.example.com',
            'media.public_base_url' => 'https://cdn.example.com/assets',
            'media.signed_url_ttl_minutes' => 0,
        ]);

        $media = $this->makeMedia();

        $url = app(MediaUrlService::class)->publicUrl($media);

        $this->assertSame('https://cdn.example.com/assets/'.$media->file_path, $url);
    }

    public function test_ensure_accessible_passes_for_reachable_media(): void
    {
        config(['app.url' => 'https://api.example.com', 'media.signed_url_ttl_minutes' => 0]);

        $media = $this->makeMedia('image/jpeg');

        Http::fake([
            '*' => Http::response('', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        app(MediaUrlService::class)->ensurePubliclyAccessible($media);

        $this->assertTrue(app(MediaUrlService::class)->isPubliclyAccessible($media));
    }

    public function test_ensure_accessible_throws_on_404(): void
    {
        config(['app.url' => 'https://api.example.com', 'media.signed_url_ttl_minutes' => 0]);

        $media = $this->makeMedia();

        Http::fake(['*' => Http::response('', 404)]);

        $this->expectException(MediaNotAccessibleException::class);
        $this->expectExceptionMessage('HTTP 404');

        app(MediaUrlService::class)->ensurePubliclyAccessible($media);
    }

    public function test_ensure_accessible_throws_on_non_media_content_type(): void
    {
        config(['app.url' => 'https://api.example.com', 'media.signed_url_ttl_minutes' => 0]);

        $media = $this->makeMedia();

        Http::fake(['*' => Http::response('<html>404</html>', 200, ['Content-Type' => 'text/html'])]);

        $this->expectException(MediaNotAccessibleException::class);
        $this->expectExceptionMessage('unexpected content type');

        app(MediaUrlService::class)->ensurePubliclyAccessible($media);
    }

    public function test_ensure_accessible_falls_back_to_ranged_get_when_head_forbidden(): void
    {
        config(['app.url' => 'https://api.example.com', 'media.signed_url_ttl_minutes' => 0]);

        $media = $this->makeMedia('video/mp4', 'clip.mp4');

        Http::fake(function ($request) {
            if ($request->method() === 'HEAD') {
                return Http::response('', 405);
            }

            return Http::response('', 206, ['Content-Type' => 'video/mp4']);
        });

        app(MediaUrlService::class)->ensurePubliclyAccessible($media);

        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->hasHeader('Range'));
    }
}
