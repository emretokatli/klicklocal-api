<?php

namespace App\Services\Ai;

use App\Support\SafeUrlFetcher;
use Illuminate\Support\Str;
use Throwable;

/**
 * Collects every website signal the WebAnalyze report needs using plain PHP —
 * NO AI calls. Returns a flat "raw data payload" array consumed by
 * WebAnalyzeReportGenerator. All fetching goes through SafeUrlFetcher, which
 * enforces the SSRF guard (http/https only, default ports, no private/reserved
 * IPs, re-checked on every redirect hop, bounded body, HTML-only).
 */
class WebsiteDataCollector
{
    private const FETCH_TIMEOUT = 12;

    private const USER_AGENT = 'KlicklocalBot/2.0 (+https://klicklocal.app)';

    public function __construct(
        private readonly SafeUrlFetcher $urlFetcher,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function collect(string $url): array
    {
        $url = $this->normalizeUrl($url);
        $html = $this->fetchHtml($url);

        if ($html === null) {
            return $this->unreachablePayload($url);
        }

        $data = $this->extractFromHtml($html, $url);
        $data['site_reachable'] = true;
        $data['final_url'] = $url;
        $data['https'] = Str::startsWith($url, 'https://');

        // Contact data lives on the Impressum / Kontakt page on German sites.
        $contact = $this->collectContactData($html, $url);
        $data = array_merge($data, $contact);

        // Local-keyword-in-title check needs a city; derive from address if any.
        $city = $this->cityFromAddresses($data['addresses'] ?? []);
        $data['has_local_keyword_in_title'] = $city !== null
            && $data['title'] !== null
            && Str::contains(Str::lower($data['title']), Str::lower($city));

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function unreachablePayload(string $url): array
    {
        return [
            'site_reachable' => false,
            'final_url' => $url,
            'https' => Str::startsWith($url, 'https://'),
            'title' => null,
            'title_length' => null,
            'meta_description' => null,
            'meta_desc_length' => null,
            'has_viewport' => false,
            'lang' => null,
            'canonical' => null,
            'og_title' => null,
            'og_image' => null,
            'robots_meta' => null,
            'h1_count' => 0,
            'h1_texts' => [],
            'img_total' => 0,
            'img_missing_alt' => 0,
            'has_local_keyword_in_title' => false,
            'cms' => [],
            'tracking' => [],
            'schema_types' => [],
            'has_local_business' => false,
            'tel_links' => [],
            'mailto_links' => [],
            'has_whatsapp' => false,
            'has_online_booking' => false,
            'has_maps_embed' => false,
            'has_opening_hours' => false,
            'form_count' => 0,
            'broken_contact_links' => [],
            'emails' => [],
            'phones' => [],
            'addresses' => [],
            'vat_ids' => [],
            'social_links' => [],
            'placeholder_social_links' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractFromHtml(string $html, string $baseUrl): array
    {
        $title = $this->firstMatch('/<title[^>]*>(.*?)<\/title>/is', $html);
        $title = $title !== null ? $this->cleanText($title) : null;

        $metaDescription = $this->metaContent($html, 'name', 'description');
        $robots = $this->metaContent($html, 'name', 'robots');
        $ogTitle = $this->metaContent($html, 'property', 'og:title');
        $ogImage = $this->metaContent($html, 'property', 'og:image');
        $viewport = $this->metaContent($html, 'name', 'viewport');
        $canonical = $this->linkHref($html, 'canonical');
        $lang = $this->firstMatch('/<html[^>]*\blang=["\']([^"\']+)["\']/i', $html);

        $h1Texts = [];
        $h1Count = (int) preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $html, $h1Matches);
        foreach ($h1Matches[1] ?? [] as $raw) {
            $text = $this->cleanText($raw);
            if ($text !== '') {
                $h1Texts[] = $text;
            }
        }

        $imgTotal = preg_match_all('/<img\b[^>]*>/i', $html);
        $imgMissingAlt = $this->countImagesMissingAlt($html);

        $links = $this->extractAnchors($html);

        [$telLinks, $mailtoLinks, $brokenLinks] = $this->contactLinks($links);
        [$socialLinks, $placeholderSocial] = $this->socialLinks($links);

        $lowerHtml = Str::lower($html);

        return [
            'title' => $title,
            'title_length' => $title !== null ? mb_strlen($title) : null,
            'meta_description' => $metaDescription,
            'meta_desc_length' => $metaDescription !== null ? mb_strlen($metaDescription) : null,
            'has_viewport' => $viewport !== null,
            'lang' => $lang,
            'canonical' => $canonical,
            'og_title' => $ogTitle,
            'og_image' => $ogImage,
            'robots_meta' => $robots,
            'h1_count' => $h1Count,
            'h1_texts' => array_slice($h1Texts, 0, 3),
            'img_total' => (int) $imgTotal,
            'img_missing_alt' => $imgMissingAlt,
            'cms' => $this->detectCms($html),
            'tracking' => $this->detectTracking($html),
            'schema_types' => $this->schemaTypes($html),
            'has_local_business' => $this->hasLocalBusiness($html),
            'tel_links' => $telLinks,
            'mailto_links' => $mailtoLinks,
            'has_whatsapp' => Str::contains($lowerHtml, ['wa.me', 'api.whatsapp.com']),
            'has_online_booking' => (bool) preg_match('/termin|buchen|booking|reservier|opentable|resmio/i', $html),
            'has_maps_embed' => Str::contains($lowerHtml, ['google.com/maps', 'maps.googleapis']),
            'has_opening_hours' => (bool) preg_match('/öffnungszeiten|opening hours/iu', $this->cleanText($html)),
            'form_count' => (int) preg_match_all('/<form\b/i', $html),
            'broken_contact_links' => $brokenLinks,
            'social_links' => $socialLinks,
            'placeholder_social_links' => $placeholderSocial,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectContactData(string $homeHtml, string $baseUrl): array
    {
        $impressumUrl = $this->findImpressumUrl($homeHtml, $baseUrl);
        $html = $homeHtml;

        if ($impressumUrl !== null && $impressumUrl !== $baseUrl) {
            $fetched = $this->fetchHtml($impressumUrl);
            if ($fetched !== null) {
                $html = $fetched;
            }
        }

        $text = $this->cleanText($html);

        $emails = $this->matchAll('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text);
        $phones = $this->matchAll('/(?:\+49|0)[\d\s()\-\/]{6,}/', $text);
        $addresses = $this->matchAll('/[A-ZÄÖÜ][\wäöüß.\- ]+\s\d+[a-z]?\s*,?\s*\d{5}\s+[A-ZÄÖÜ][\wäöüß\- ]+/u', $text);
        $vatIds = $this->matchAll('/DE\s?\d{9}/', $text);

        return [
            'emails' => array_values(array_unique($emails)),
            'phones' => array_values(array_unique(array_map('trim', $phones))),
            'addresses' => array_values(array_unique(array_map('trim', $addresses))),
            'vat_ids' => array_values(array_unique(array_map(fn ($v) => str_replace(' ', '', $v), $vatIds))),
        ];
    }

    private function findImpressumUrl(string $html, string $baseUrl): ?string
    {
        foreach ($this->extractAnchors($html) as $link) {
            if (preg_match('/impressum|kontakt|imprint/i', $link['text'].' '.$link['href']) === 1) {
                return $this->absoluteUrl($link['href'], $baseUrl);
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function detectCms(string $html): array
    {
        $map = [
            'WordPress' => ['wp-content', 'wp-includes'],
            'Wix' => ['wixstatic', 'wix.com'],
            'Jimdo' => ['jimdo'],
            'TYPO3' => ['typo3'],
            'Joomla' => ['joomla'],
            'Shopify' => ['cdn/shop', 'shopify'],
            'Squarespace' => ['squarespace'],
            'Webflow' => ['webflow'],
            'Elementor' => ['elementor'],
            'Divi' => ['et_pb_'],
            'Next.js' => ['__NEXT_DATA__'],
        ];

        $lower = Str::lower($html);
        $found = [];

        foreach ($map as $label => $needles) {
            foreach ($needles as $needle) {
                if (Str::contains($lower, Str::lower($needle))) {
                    $found[] = $label;
                    break;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @return string[]
     */
    private function detectTracking(string $html): array
    {
        $lower = Str::lower($html);
        $found = [];

        $checks = [
            'Google Tag Manager' => fn () => Str::contains($lower, 'googletagmanager.com/gtm.js'),
            'Google Analytics 4' => fn () => Str::contains($lower, 'gtag(') || Str::contains($lower, 'googletagmanager.com/gtag'),
            'Universal Analytics' => fn () => Str::contains($lower, 'google-analytics.com/analytics.js'),
            'Google Ads' => fn () => Str::contains($lower, 'googleadservices'),
            'Meta Pixel' => fn () => Str::contains($lower, 'connect.facebook.net'),
            'TikTok Pixel' => fn () => Str::contains($lower, 'tiktok.com/i18n/pixel'),
            'Hotjar' => fn () => Str::contains($lower, 'static.hotjar.com'),
            'Matomo' => fn () => Str::contains($lower, 'matomo') || Str::contains($lower, 'piwik'),
            'Consent: Cookiebot' => fn () => Str::contains($lower, 'cookiebot'),
            'Consent: Usercentrics' => fn () => Str::contains($lower, 'usercentrics'),
            'Consent: Borlabs' => fn () => Str::contains($lower, 'borlabs'),
            'Consent: Complianz' => fn () => Str::contains($lower, 'complianz'),
        ];

        foreach ($checks as $label => $check) {
            if ($check()) {
                $found[] = $label;
            }
        }

        return $found;
    }

    /**
     * @return string[]
     */
    private function schemaTypes(string $html): array
    {
        $types = [];

        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches) > 0) {
            foreach ($matches[1] as $block) {
                $decoded = json_decode(trim($block), true);
                if (! is_array($decoded)) {
                    continue;
                }

                $this->collectSchemaTypes($decoded, $types);
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * @param  array<mixed>  $node
     * @param  string[]  $types
     */
    private function collectSchemaTypes(array $node, array &$types): void
    {
        foreach ($node as $key => $value) {
            if ($key === '@type') {
                foreach ((array) $value as $type) {
                    if (is_string($type) && $type !== '') {
                        $types[] = $type;
                    }
                }
            } elseif (is_array($value)) {
                $this->collectSchemaTypes($value, $types);
            }
        }
    }

    private function hasLocalBusiness(string $html): bool
    {
        foreach ($this->schemaTypes($html) as $type) {
            if (Str::contains(Str::lower($type), 'localbusiness')
                || in_array($type, ['Restaurant', 'Dentist', 'Store', 'Bakery', 'BarOrPub', 'CafeOrCoffeeShop'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{text: string, href: string}>  $links
     * @return array{0: string[], 1: string[], 2: array<int, array{display: string, href: string}>}
     */
    private function contactLinks(array $links): array
    {
        $tel = [];
        $mailto = [];
        $broken = [];

        foreach ($links as $link) {
            $href = $link['href'];
            $hrefLower = Str::lower($href);

            $isTel = Str::startsWith($hrefLower, 'tel:');
            $isMail = Str::startsWith($hrefLower, 'mailto:');

            if (! $isTel && ! $isMail) {
                continue;
            }

            if ($isTel) {
                $tel[] = $href;
            } else {
                $mailto[] = $href;
            }

            $placeholder = Str::contains($hrefLower, ['domain.com', 'example', '987654321', 'mustermann', 'your@email']);

            $target = preg_replace('/[^0-9a-z@.]/', '', str_replace(['tel:', 'mailto:'], '', $hrefLower)) ?? '';
            $display = preg_replace('/[^0-9a-z@.]/', '', Str::lower($link['text'])) ?? '';
            $mismatch = $display !== '' && $target !== ''
                && ! Str::contains($target, $display)
                && ! Str::contains($display, $target);

            if ($placeholder || $mismatch) {
                $broken[] = ['display' => $link['text'], 'href' => $href];
            }
        }

        return [array_values(array_unique($tel)), array_values(array_unique($mailto)), $broken];
    }

    /**
     * @param  array<int, array{text: string, href: string}>  $links
     * @return array{0: string[], 1: string[]}
     */
    private function socialLinks(array $links): array
    {
        $hosts = ['facebook.com', 'instagram.com', 'linkedin.com', 'youtube.com', 'tiktok.com', 'x.com', 'twitter.com', 'xing.com'];
        $builderDefaults = ['/wix', '/jimdo', '/squarespace', '/webflow'];

        $real = [];
        $placeholder = [];

        foreach ($links as $link) {
            $href = $link['href'];
            $lower = Str::lower($href);

            if (! Str::contains($lower, $hosts)) {
                continue;
            }

            $isBuilderDefault = false;
            foreach ($builderDefaults as $marker) {
                if (Str::contains($lower, $marker)) {
                    $isBuilderDefault = true;
                    break;
                }
            }

            if ($isBuilderDefault) {
                $placeholder[] = $href;
            } else {
                $real[] = $href;
            }
        }

        return [array_values(array_unique($real)), array_values(array_unique($placeholder))];
    }

    /**
     * @return array<int, array{text: string, href: string}>
     */
    private function extractAnchors(string $html): array
    {
        $anchors = [];

        if (preg_match_all('/<a\b[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $anchors[] = [
                    'href' => html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'text' => $this->cleanText($match[2]),
                ];
            }
        }

        return $anchors;
    }

    private function countImagesMissingAlt(string $html): int
    {
        $missing = 0;

        if (preg_match_all('/<img\b[^>]*>/i', $html, $matches) > 0) {
            foreach ($matches[0] as $tag) {
                if (preg_match('/\balt=["\'][^"\']+["\']/i', $tag) !== 1) {
                    $missing++;
                }
            }
        }

        return $missing;
    }

    private function metaContent(string $html, string $attr, string $value): ?string
    {
        $patterns = [
            '/<meta[^>]*\b'.preg_quote($attr, '/').'=["\']'.preg_quote($value, '/').'["\'][^>]*\bcontent=["\']([^"\']*)["\']/i',
            '/<meta[^>]*\bcontent=["\']([^"\']*)["\'][^>]*\b'.preg_quote($attr, '/').'=["\']'.preg_quote($value, '/').'["\']/i',
        ];

        foreach ($patterns as $pattern) {
            $match = $this->firstMatch($pattern, $html);
            if ($match !== null) {
                $clean = $this->cleanText($match);

                return $clean !== '' ? $clean : null;
            }
        }

        return null;
    }

    private function linkHref(string $html, string $rel): ?string
    {
        $patterns = [
            '/<link[^>]*\brel=["\']'.preg_quote($rel, '/').'["\'][^>]*\bhref=["\']([^"\']*)["\']/i',
            '/<link[^>]*\bhref=["\']([^"\']*)["\'][^>]*\brel=["\']'.preg_quote($rel, '/').'["\']/i',
        ];

        foreach ($patterns as $pattern) {
            $match = $this->firstMatch($pattern, $html);
            if ($match !== null && trim($match) !== '') {
                return trim($match);
            }
        }

        return null;
    }

    /**
     * @param  string[]  $addresses
     */
    private function cityFromAddresses(array $addresses): ?string
    {
        foreach ($addresses as $address) {
            if (preg_match('/\d{5}\s+([A-ZÄÖÜ][\wäöüß\- ]+)/u', $address, $match) === 1) {
                return trim($match[1]);
            }
        }

        return null;
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            return $this->urlFetcher->fetch($url, timeout: self::FETCH_TIMEOUT, headers: [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml',
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    private function absoluteUrl(string $href, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $href) === 1) {
            return $href;
        }

        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';

        if (Str::startsWith($href, '//')) {
            return "{$scheme}:{$href}";
        }

        if (Str::startsWith($href, '/')) {
            return "{$scheme}://{$host}{$href}";
        }

        return "{$scheme}://{$host}/".ltrim($href, '/');
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://'.$url;
        }

        return $url;
    }

    private function firstMatch(string $pattern, string $subject): ?string
    {
        if (preg_match($pattern, $subject, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function matchAll(string $pattern, string $subject): array
    {
        if (preg_match_all($pattern, $subject, $matches) > 0) {
            return $matches[0];
        }

        return [];
    }

    private function cleanText(string $value): string
    {
        $text = preg_replace('/<[^>]+>/', ' ', $value) ?? $value;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
