<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetches user-supplied URLs with SSRF protections: http(s) on default ports
 * only, no private/reserved/loopback/link-local targets (re-validated on every
 * redirect hop), bounded body size, and HTML-only content types.
 */
class SafeUrlFetcher
{
    public const MAX_BODY_BYTES = 2 * 1024 * 1024;

    public const MAX_REDIRECTS = 3;

    /** @var (callable(string): list<string>)|null */
    private $resolveHostUsing;

    /**
     * @param  (callable(string): list<string>)|null  $resolveHostUsing  custom DNS resolver (host => IPs), used in tests
     */
    public function __construct(?callable $resolveHostUsing = null)
    {
        $this->resolveHostUsing = $resolveHostUsing;
    }

    /**
     * @param  array<string, string>  $headers
     *
     * @throws RuntimeException when the URL is unsafe or the response unacceptable
     */
    public function fetch(string $url, int $timeout = 15, array $headers = []): string
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $ips = $this->assertSafeUrl($current);

            $parts = parse_url($current);
            $scheme = strtolower($parts['scheme'] ?? 'https');
            $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
            $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
            $ip = $ips[0];

            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->withOptions([
                    'allow_redirects' => false,
                    // Pin the connection to the IP we just validated so cURL does
                    // not re-resolve the hostname (DNS rebinding / TOCTOU). The
                    // hostname is kept for the Host header and TLS SNI, so
                    // certificate verification still applies.
                    'curl' => [
                        CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"],
                    ],
                ])
                ->get($current);

            if ($response->redirect()) {
                $location = (string) $response->header('Location');

                if ($location === '') {
                    throw new RuntimeException('Redirect without Location header.');
                }

                $current = $this->resolveRedirectTarget($current, $location);

                continue;
            }

            if ($response->failed()) {
                throw new RuntimeException("Request failed with HTTP {$response->status()}.");
            }

            $this->assertHtmlResponse($response);

            return $this->boundedBody($response);
        }

        throw new RuntimeException('Too many redirects.');
    }

    /**
     * @return list<string> the validated (public) IPs the host resolves to
     */
    private function assertSafeUrl(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            throw new RuntimeException('Invalid URL.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('Only http/https URLs are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('URLs with embedded credentials are not allowed.');
        }

        $defaultPort = $scheme === 'https' ? 443 : 80;

        if (isset($parts['port']) && $parts['port'] !== $defaultPort) {
            throw new RuntimeException('Non-default ports are not allowed.');
        }

        $host = strtolower(trim($parts['host'], '[]'));

        $ips = $this->resolveHost($host);

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw new RuntimeException('URL resolves to a non-public address.');
            }
        }

        return $ips;
    }

    /**
     * @return list<string>
     */
    private function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if ($this->resolveHostUsing !== null) {
            $ips = ($this->resolveHostUsing)($host);
        } else {
            $ips = gethostbynamel($host) ?: [];

            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (! empty($record['ipv6'])) {
                    $ips[] = (string) $record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            throw new RuntimeException('Hostname could not be resolved.');
        }

        return array_values($ips);
    }

    private function isPublicIp(string $ip): bool
    {
        // NO_PRIV_RANGE: 10/8, 172.16/12, 192.168/16, fc00::/7
        // NO_RES_RANGE: 0/8, 127/8, 169.254/16, 240/4, ::1, ::, ::ffff:0:0/96, fe80::/10
        if (filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false) {
            return false;
        }

        // Defence in depth for IPv4-mapped IPv6 (e.g. ::ffff:127.0.0.1 and the
        // hex form ::ffff:7f00:1): decode the embedded IPv4 and re-check it,
        // since the filter validates the v6 form as a single opaque unit.
        $packed = @inet_pton($ip);

        if ($packed !== false && strlen($packed) === 16
            && substr($packed, 0, 12) === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff") {
            $embedded = inet_ntop(substr($packed, 12, 4));

            return $embedded !== false && filter_var(
                $embedded,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) !== false;
        }

        return true;
    }

    private function resolveRedirectTarget(string $current, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($current);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';

        if (str_starts_with($location, '//')) {
            return "{$scheme}:{$location}";
        }

        if (str_starts_with($location, '/')) {
            return "{$scheme}://{$host}{$location}";
        }

        $path = $parts['path'] ?? '/';
        $lastSlash = strrpos($path, '/');
        $dir = $lastSlash === false ? '' : rtrim(substr($path, 0, $lastSlash + 1), '/');

        return "{$scheme}://{$host}{$dir}/{$location}";
    }

    private function assertHtmlResponse(Response $response): void
    {
        $contentType = strtolower((string) $response->header('Content-Type'));

        if (
            $contentType !== ''
            && ! str_contains($contentType, 'text/html')
            && ! str_contains($contentType, 'application/xhtml+xml')
        ) {
            throw new RuntimeException("Unsupported content type: {$contentType}.");
        }
    }

    private function boundedBody(Response $response): string
    {
        if ((int) $response->header('Content-Length') > self::MAX_BODY_BYTES) {
            throw new RuntimeException('Response body too large.');
        }

        $body = (string) $response->body();

        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new RuntimeException('Response body too large.');
        }

        return $body;
    }
}
