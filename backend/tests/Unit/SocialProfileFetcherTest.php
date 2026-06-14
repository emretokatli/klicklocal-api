<?php

namespace Tests\Unit;

use App\Services\Ai\SocialProfileFetcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialProfileFetcherTest extends TestCase
{
    public function test_extracts_instagram_metrics_from_public_html(): void
    {
        $html = 'window._sharedData = {"edge_followed_by":{"count":1234},'
            .'"edge_owner_to_timeline_media":{"count":87},'
            .'"taken_at_timestamp":1700000000};';

        Http::fake([
            'https://www.instagram.com/example_gastro/' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        ]);

        $result = (new SocialProfileFetcher)->fetchInstagram('example_gastro');

        $this->assertSame('example_gastro', $result['handle']);
        $this->assertTrue($result['exists']);
        $this->assertSame(1234, $result['followers']);
        $this->assertSame(87, $result['post_count']);
        $this->assertSame('2023-11-14', $result['last_post']);
        $this->assertNull($result['error']);
        $this->assertNotNull($result['posts_per_week']);
    }

    public function test_handles_full_url_and_login_wall(): void
    {
        Http::fake([
            'https://www.instagram.com/walled/' => Http::response(
                '<html><body>Bitte logge dich ein, um Instagram zu sehen.</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $result = (new SocialProfileFetcher)->fetchInstagram('https://instagram.com/walled/');

        $this->assertSame('walled', $result['handle']);
        $this->assertTrue($result['exists']);
        $this->assertNull($result['followers']);
        $this->assertNull($result['post_count']);
        $this->assertSame('could not scrape', $result['error']);
    }
}
