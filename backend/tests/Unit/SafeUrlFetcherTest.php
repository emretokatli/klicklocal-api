<?php

namespace Tests\Unit;

use App\Support\SafeUrlFetcher;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SafeUrlFetcherTest extends TestCase
{
    /**
     * @param  array<string, list<string>>  $dns
     */
    private function fetcher(array $dns = []): SafeUrlFetcher
    {
        return new SafeUrlFetcher(
            fn (string $host): array => $dns[$host] ?? throw new RuntimeException("Unexpected DNS lookup: {$host}"),
        );
    }

    public function test_happy_path_returns_html_body(): void
    {
        Http::fake([
            'https://example.de' => Http::response('<html><body>Hallo</body></html>', 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]),
        ]);

        $body = $this->fetcher(['example.de' => ['93.184.216.34']])->fetch('https://example.de');

        $this->assertStringContainsString('Hallo', $body);
    }

    public function test_rejects_private_and_reserved_literal_ips(): void
    {
        Http::fake();

        foreach ([
            'http://127.0.0.1/admin',
            'http://10.0.0.5/',
            'http://172.16.1.1/',
            'http://192.168.1.1/',
            'http://169.254.169.254/latest/meta-data',
            'http://[::1]/',
        ] as $url) {
            try {
                $this->fetcher()->fetch($url);
                $this->fail("Expected RuntimeException for {$url}");
            } catch (RuntimeException) {
                // expected
            }
        }

        Http::assertNothingSent();
    }

    public function test_rejects_hostname_resolving_to_private_ip(): void
    {
        Http::fake();

        $this->expectException(RuntimeException::class);

        try {
            $this->fetcher(['internal.example.de' => ['192.168.10.20']])
                ->fetch('https://internal.example.de');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_rejects_redirect_to_private_address(): void
    {
        Http::fake([
            'https://public.example.de' => Http::response('', 302, [
                'Location' => 'http://127.0.0.1/internal',
            ]),
        ]);

        $this->expectException(RuntimeException::class);

        try {
            $this->fetcher(['public.example.de' => ['93.184.216.34']])
                ->fetch('https://public.example.de');
        } finally {
            // only the first (public) request may have been sent
            Http::assertSentCount(1);
        }
    }

    public function test_follows_safe_redirects(): void
    {
        Http::fake([
            'https://old.example.de' => Http::response('', 301, [
                'Location' => 'https://new.example.de/start',
            ]),
            'https://new.example.de/start' => Http::response('<html>Neu</html>', 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $body = $this->fetcher([
            'old.example.de' => ['93.184.216.34'],
            'new.example.de' => ['93.184.216.35'],
        ])->fetch('https://old.example.de');

        $this->assertStringContainsString('Neu', $body);
    }

    public function test_rejects_too_many_redirects(): void
    {
        Http::fake([
            'https://loop.example.de/*' => Http::response('', 302, [
                'Location' => 'https://loop.example.de/again',
            ]),
            'https://loop.example.de' => Http::response('', 302, [
                'Location' => 'https://loop.example.de/again',
            ]),
        ]);

        $this->expectExceptionMessage('Too many redirects.');

        $this->fetcher(['loop.example.de' => ['93.184.216.34']])
            ->fetch('https://loop.example.de');
    }

    public function test_rejects_oversized_body(): void
    {
        Http::fake([
            'https://big.example.de' => Http::response(
                str_repeat('a', SafeUrlFetcher::MAX_BODY_BYTES + 1),
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $this->expectExceptionMessage('Response body too large.');

        $this->fetcher(['big.example.de' => ['93.184.216.34']])
            ->fetch('https://big.example.de');
    }

    public function test_rejects_non_html_content_type(): void
    {
        Http::fake([
            'https://api.example.de' => Http::response('{"a":1}', 200, [
                'Content-Type' => 'application/json',
            ]),
        ]);

        $this->expectExceptionMessage('Unsupported content type');

        $this->fetcher(['api.example.de' => ['93.184.216.34']])
            ->fetch('https://api.example.de');
    }

    public function test_rejects_non_http_schemes_and_non_default_ports(): void
    {
        Http::fake();

        foreach ([
            'ftp://example.de/file',
            'file:///etc/passwd',
            'https://example.de:8443/',
            'http://example.de:6379/',
        ] as $url) {
            try {
                $this->fetcher(['example.de' => ['93.184.216.34']])->fetch($url);
                $this->fail("Expected RuntimeException for {$url}");
            } catch (RuntimeException) {
                // expected
            }
        }

        Http::assertNothingSent();
    }
}
