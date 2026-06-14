<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\WebAnalyzeClientInterface;
use App\Services\Ai\DTOs\WebAnalyzeResultDTO;
use Illuminate\Support\Str;

class FakeWebAnalyzeClient implements WebAnalyzeClientInterface
{
    public function analyze(string $website): WebAnalyzeResultDTO
    {
        $url = $this->normalizeUrl($website);
        $host = parse_url($url, PHP_URL_HOST) ?: $url;

        $report = <<<MD
# Lead-Analyse: {$host} — {$url}

## Gesamtbewertung: 52/100 — Ausbaufähig
| Kategorie | Punkte |
|---|---|
| Technik & Infrastruktur | 9/15 |
| SEO | 14/25 |
| Content & Vertrauen | 8/15 |
| Conversion | 11/20 |
| Marketing-Reife | 7/15 |
| Eigenständigkeit | 3/10 |

## Stärken
- Eigene Domain unter {$host} erreichbar
- HTTPS aktiv
- Grundlegende Unternehmensinformationen vorhanden

## Schwächen & Probleme
- Meta Description fehlt oder ist zu kurz (lokale Keywords nicht sichtbar)
- Kein LocalBusiness Schema.org Markup erkannt
- Tracking/Analytics nicht nachweisbar — Online-Marketing-Reife unklar

## Verbesserungspotenziale (priorisiert)
1. [Quick Win] Meta Title und Description mit Branche + Stadt optimieren
2. LocalBusiness Schema.org auf Kontakt/Startseite ergänzen
3. Funktionierenden tel:-Link und klares Kontaktformular im Above-the-fold-Bereich

## SEO-Bewertung
Title/Description und Überschriftenstruktur wirken ausbaufähig; lokale Signale fehlen teilweise. SEO-Teilergebnis: 14/25.

## Kontaktdaten (CRM)
Firma/Inhaber: (Platzhalter — echte Analyse mit WEBANALYZE_DRIVER=api)
Adresse: —
Telefon: —
E-Mail: —
USt-IdNr: —

## Lokales Marktpotenzial
Marktdichte und Sichtbarkeit konnten im Fake-Modus nicht per Web-Recherche ermittelt werden.

## Wachstumsprognose
| Horizont | Wachstum | Umsatzpotenzial |
|---|---|---|
| 3 Monate | +2–5 % | ~800–2.000 € |
| 6 Monate | +5–10 % | ~2.000–5.000 € |

Annahmen: Branchendurchschnitt, keine echten Umsatzdaten
*Schätzung — keine Garantie; präzisierbar mit echten Kundendaten.*

## Gesprächsaufhänger (3–5 konkrete Punkte)
1. „Ihre Website ist online, aber Google sieht noch nicht klar, *was* Sie lokal anbieten — das kostet Sichtbarkeit.“
2. „Konkurrenten mit Sterne-Bewertungen und Öffnungszeiten in Google haben einen Vorsprung — technisch lösbar.“
3. „Ohne Analytics wissen Sie nicht, wie viele Anfragen über die Website verloren gehen.“

---
*Platzhalter aus WEBANALYZE_DRIVER=fake. Setze ANTHROPIC_API_KEY und WEBANALYZE_DRIVER=api für echte Agent-SDK-Analyse.*
MD;

        return new WebAnalyzeResultDTO(
            website: $url,
            reportMarkdown: $report,
            score: 52,
            band: 'Ausbaufähig',
            sessionId: 'fake-'.Str::uuid(),
            durationMs: 0,
            model: 'fake-klicklocal-webanalyze',
        );
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://'.$url;
        }

        return $url;
    }
}
