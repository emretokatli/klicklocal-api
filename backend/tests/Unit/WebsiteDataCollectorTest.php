<?php

namespace Tests\Unit;

use App\Services\Ai\WebsiteDataCollector;
use App\Support\SafeUrlFetcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteDataCollectorTest extends TestCase
{
    private function collector(): WebsiteDataCollector
    {
        // Resolve every hostname to a public IP so the SSRF guard allows it.
        return new WebsiteDataCollector(
            new SafeUrlFetcher(fn (string $host): array => ['93.184.216.34']),
        );
    }

    private function homeHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
  <title>Restaurant Beispiel München - Beste Küche</title>
  <meta name="description" content="Türkisches Restaurant in München mit frischer Küche.">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="index,follow">
  <meta property="og:title" content="Restaurant Beispiel München">
  <meta property="og:image" content="https://example-gastro.de/og.jpg">
  <link rel="canonical" href="https://example-gastro.de/">
  <script src="https://www.googletagmanager.com/gtm.js?id=GTM-XXXX"></script>
  <script>gtag('config', 'G-XXXX');</script>
  <script async src="https://connect.facebook.net/en_US/fbevents.js"></script>
  <script src="https://consent.cookiebot.com/uc.js"></script>
  <script type="application/ld+json">{"@type":"Restaurant","name":"Restaurant Beispiel"}</script>
</head>
<body>
  <h1>Willkommen</h1>
  <img src="a.jpg" alt="Gericht">
  <img src="b.jpg">
  <a href="tel:+4989123456">+4989123456</a>
  <a href="tel:+49987654321">Jetzt anrufen</a>
  <a href="https://instagram.com/example_gastro">Instagram</a>
  <a href="https://twitter.com/Wix">Twitter</a>
  <a href="/impressum">Impressum</a>
  <form><input name="q"></form>
</body>
</html>
HTML;
    }

    private function impressumHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html><html lang="de"><body>
Mustermann GmbH, Beispielstraße 12, 80331 München.
Telefon: +49 89 123456
E-Mail: info@example-gastro.de
USt-IdNr: DE123456789
</body></html>
HTML;
    }

    public function test_happy_path_extracts_core_signals(): void
    {
        Http::fake([
            'https://example-gastro.de' => Http::response($this->homeHtml(), 200, ['Content-Type' => 'text/html']),
            'https://example-gastro.de/impressum' => Http::response($this->impressumHtml(), 200, ['Content-Type' => 'text/html']),
        ]);

        $data = $this->collector()->collect('example-gastro.de');

        $this->assertTrue($data['site_reachable']);
        $this->assertTrue($data['https']);
        $this->assertSame('Restaurant Beispiel München - Beste Küche', $data['title']);
        $this->assertSame('Türkisches Restaurant in München mit frischer Küche.', $data['meta_description']);
        $this->assertTrue($data['has_viewport']);
        $this->assertSame('de', $data['lang']);
        $this->assertSame('https://example-gastro.de/', $data['canonical']);
        $this->assertSame(1, $data['h1_count']);
        $this->assertSame(2, $data['img_total']);
        $this->assertSame(1, $data['img_missing_alt']);

        $this->assertContains('Google Tag Manager', $data['tracking']);
        $this->assertContains('Google Analytics 4', $data['tracking']);
        $this->assertContains('Meta Pixel', $data['tracking']);
        $this->assertContains('Consent: Cookiebot', $data['tracking']);

        $this->assertContains('Restaurant', $data['schema_types']);
        $this->assertTrue($data['has_local_business']);

        $this->assertContains('info@example-gastro.de', $data['emails']);
        $this->assertContains('DE123456789', $data['vat_ids']);
        $this->assertNotEmpty($data['addresses']);
        $this->assertTrue($data['has_local_keyword_in_title']);
        $this->assertSame(1, $data['form_count']);
    }

    public function test_site_down_returns_unreachable(): void
    {
        Http::fake([
            'https://offline.de' => Http::response('', 503, ['Content-Type' => 'text/html']),
        ]);

        $data = $this->collector()->collect('offline.de');

        $this->assertFalse($data['site_reachable']);
        $this->assertNull($data['title']);
        $this->assertSame([], $data['tracking']);
        $this->assertSame([], $data['social_links']);
    }

    public function test_placeholder_social_links_are_rejected(): void
    {
        Http::fake([
            'https://example-gastro.de' => Http::response($this->homeHtml(), 200, ['Content-Type' => 'text/html']),
            'https://example-gastro.de/impressum' => Http::response($this->impressumHtml(), 200, ['Content-Type' => 'text/html']),
        ]);

        $data = $this->collector()->collect('example-gastro.de');

        $this->assertContains('https://instagram.com/example_gastro', $data['social_links']);
        $this->assertContains('https://twitter.com/Wix', $data['placeholder_social_links']);
        $this->assertNotContains('https://twitter.com/Wix', $data['social_links']);
    }

    public function test_broken_contact_link_is_detected(): void
    {
        Http::fake([
            'https://example-gastro.de' => Http::response($this->homeHtml(), 200, ['Content-Type' => 'text/html']),
            'https://example-gastro.de/impressum' => Http::response($this->impressumHtml(), 200, ['Content-Type' => 'text/html']),
        ]);

        $data = $this->collector()->collect('example-gastro.de');

        $hrefs = array_column($data['broken_contact_links'], 'href');
        $this->assertContains('tel:+49987654321', $hrefs);
    }

    public function test_redirect_to_private_ip_is_rejected(): void
    {
        Http::fake([
            'https://ssrf.de' => Http::response('', 302, ['Location' => 'http://192.168.1.1/internal']),
        ]);

        $data = $this->collector()->collect('ssrf.de');

        $this->assertFalse($data['site_reachable']);
    }
}
