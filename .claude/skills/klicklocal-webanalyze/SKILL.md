---
name: klicklocal-webanalyze
description: Analyze and score business websites for lead research, lead qualification, and competitor analysis, producing a scored report (X/100) with strengths, issues, improvement areas, an SEO assessment, a social media activity audit, a Google Business Profile check, local market & competitor context, a "So hilft Klicklocal" pitch mapping, and a 3/6-month growth estimate with financial value. Trigger whenever the user wants to evaluate a (potential) client's or competitor's website — e.g. "check this lead", "analyze/score this site", "what CMS / tech stack do they use", "do they have tracking installed", "pull their contact details", "compare these competitors", "quick SEO check", "how much potential does this business have" — or any request to assess how professional, well-optimized, or digitally mature a local business website is.
---

# KlickLocal WebAnalyze (v2)

Analyze, score, and qualify business websites using Python code execution and browser automation. Built for three jobs:

1. **Lead research** — assess a prospect's website + social presence + Google visibility and deliver a scored report (X/100) with strengths, issues, improvement areas, growth potential, and a Klicklocal pitch mapping.
2. **Competitor analysis** — run the same checks across multiple sites and produce a side-by-side comparison with scores and a verdict.
3. **Market potential** — estimate what the business could gain (customers, revenue) over 3 and 6 months if the identified issues were fixed.

All code runs in a persistent namespace via `execute_code`. All browser functions are async — use `await`. Variables persist between calls, so build the analysis incrementally and reuse helpers across sites.

## Non-negotiable rules (read first)

1. **Steps 8 (social audit), 9 (Google visibility) and 11a (market context) are MANDATORY.** Never skip them "for budget reasons". If constrained, run the minimum version defined in each step — but run it. A Klicklocal lead report without competitor and social data misses the product's core pitch.
2. **The deliverable is customer-facing by default.** Never include model name, runtime, cost, internal scoring debates, or phrases like "aus Budgetgründen ausgelassen" in the report body. Internal metadata goes ONLY into the separate "Interne Notizen" block (see step 12).
3. **Unverifiable = half points + "nicht verifizierbar" label.** Apply this consistently — do not silently treat unverifiable items as failing or passing.
4. **The Wachstumsprognose MUST always include its Annahmen line** (Ø Bon, baseline, levers). A forecast without visible assumptions is invalid output.
5. **Every competitor claim must be backed by a named example.** Not "Ihre Wettbewerber zeigen Sterne" but "Restaurant X (4,6★, 312 Bewertungen) erscheint vor Ihnen im Maps-Pack".

## Core workflow (single site)

Run steps 1–7 to collect website data, step 8 for social media, step 9 for Google visibility, step 10 to score, step 11 for market potential, step 12 for the report.

### Step 1 — Navigate and get an overview

```python
await navigate('https://example-business.de')
state = await browser.get_browser_state_summary()
print(f'Title: {state.title}')
print(f'URL: {state.url}')
print(f'Interactive elements: {len(state.dom_state.selector_map)}')
```

If the site redirects (http→https, www, language), note the final URL — redirect hygiene is itself a data point.

**Mobile check:** after the desktop pass, take one screenshot at mobile viewport (390×844) if the tooling allows. Note overlapping elements, unreadable text, or broken nav. A "your site on a phone" screenshot is the most demonstrable artifact in a sales meeting.

**Fallback — site down or unreachable:** if the domain returns an error (5xx, timeout, DNS), don't abort. Record the error (it's often the single best talking point: "your website is down right now"), then look for satellite presences: web-search the business name + city to find directory/platform pages (Lieferando, Google profile, Facebook, branch portals) and analyze those instead. A business whose only working web presence is a third-party platform page is a high-opportunity lead — score it accordingly (it loses most "Eigenständigkeit" points, see step 10).

### Step 2 — Metadata and SEO basics

```python
meta = await evaluate('''
(function(){
  const q = (s) => document.querySelector(s);
  return {
    title: document.title,
    titleLength: document.title.length,
    description: q('meta[name="description"]')?.content || null,
    descriptionLength: (q('meta[name="description"]')?.content || '').length,
    canonical: q('link[rel="canonical"]')?.href || null,
    robots: q('meta[name="robots"]')?.content || null,
    ogTitle: q('meta[property="og:title"]')?.content || null,
    ogImage: q('meta[property="og:image"]')?.content || null,
    viewport: q('meta[name="viewport"]')?.content || null,
    lang: document.documentElement.lang || null,
    https: location.protocol === 'https:',
    h1Count: document.querySelectorAll('h1').length,
    imgTotal: document.querySelectorAll('img').length,
    imgMissingAlt: document.querySelectorAll('img:not([alt]), img[alt=""]').length
  };
})()
''')
import json; print(json.dumps(meta, indent=2))
```

Red flags: missing/empty description, title >60 or <15 chars, **no local keywords in title/description** (city, district, service), no viewport meta, no HTTPS, zero or multiple h1, many images without alt text, missing `lang`.

If the head cannot be read (some builders), retry once via raw HTML fetch (`requests.get` on the URL, parse `<title>`/`<meta>` with a regex) before declaring it "nicht verifizierbar".

### Step 3 — Tech stack: CMS, frameworks, builders

Detecting the CMS tells you how easy a relaunch or optimization will be and who likely built the site.

```python
tech = await evaluate('''
(function(){
  const t = [];
  const html = document.documentElement.outerHTML;
  const gen = document.querySelector('meta[name="generator"]')?.content;
  if (gen) t.push('generator: ' + gen);
  if (html.includes('wp-content') || html.includes('wp-includes')) t.push('WordPress');
  if (html.includes('Joomla')) t.push('Joomla');
  if (html.includes('typo3')) t.push('TYPO3');
  if (html.includes('cdn/shop') || window.Shopify) t.push('Shopify');
  if (html.includes('jimdo') || html.includes('Jimdo')) t.push('Jimdo');
  if (html.includes('wixstatic') || html.includes('wix.com')) t.push('Wix');
  if (html.includes('squarespace')) t.push('Squarespace');
  if (html.includes('webflow')) t.push('Webflow');
  if (html.includes('elementor')) t.push('Elementor (page builder)');
  if (html.includes('Divi') || html.includes('et_pb_')) t.push('Divi (page builder)');
  if (window.__NEXT_DATA__) t.push('Next.js');
  if (window.__NUXT__) t.push('Nuxt.js');
  if (document.querySelector('#__next') || document.querySelector('[data-reactroot]')) t.push('React');
  if (document.querySelector('[ng-version]')) t.push('Angular');
  if (window.Vue) t.push('Vue.js');
  if (window.jQuery) t.push('jQuery ' + (window.jQuery.fn?.jquery || ''));
  // White-label platform sites (Lieferando satellites etc.)
  if (html.includes('lieferando') || html.includes('takeaway.com')) t.push('Lieferando white-label (platform-operated!)');
  return t;
})()
''')
print(f'Tech stack: {tech}')
```

### Step 4 — Marketing & tracking maturity

The strongest lead-qualification signal: a business with no analytics and no pixels is usually not running online marketing yet (opportunity), while heavy tracking suggests an existing agency relationship.

```python
tracking = await evaluate('''
(function(){
  const html = document.documentElement.outerHTML;
  const found = [];
  if (html.includes('googletagmanager.com/gtm.js') || window.google_tag_manager) found.push('Google Tag Manager');
  if (html.match(/gtag\(|googletagmanager.com\/gtag/)) found.push('Google Analytics 4 (gtag)');
  if (html.includes('google-analytics.com/analytics.js') || window.ga) found.push('Universal Analytics (legacy!)');
  if (html.includes('googleads') || html.includes('googleadservices')) found.push('Google Ads conversion');
  if (html.includes('connect.facebook.net') || window.fbq) found.push('Meta Pixel');
  if (document.querySelector('meta[name="facebook-domain-verification"]')) found.push('Meta domain verification (Business Manager set up)');
  if (document.querySelector('meta[name="google-site-verification"]')) found.push('Google Search Console verified');
  if (html.includes('static.hotjar.com') || window.hj) found.push('Hotjar');
  if (html.includes('matomo') || html.includes('piwik')) found.push('Matomo');
  if (html.includes('linkedin.com/li.lms') || html.includes('snap.licdn.com')) found.push('LinkedIn Insight');
  if (html.includes('tiktok.com/i18n/pixel') || window.ttq) found.push('TikTok Pixel');
  // Consent tools (DSGVO signal)
  if (html.includes('cookiebot')) found.push('Consent: Cookiebot');
  if (html.includes('usercentrics')) found.push('Consent: Usercentrics');
  if (html.includes('borlabs')) found.push('Consent: Borlabs');
  if (html.includes('complianz')) found.push('Consent: Complianz');
  return found;
})()
''')
print(f'Tracking & consent: {tracking}')
```

A consent banner may block trackers until accepted, so check raw HTML for script URLs rather than only live `window` objects. Tracking found in HTML but not active = installed but consent-gated, which is normal in Germany.

### Step 5 — Structured data (schema.org)

LocalBusiness markup matters for local SEO — its absence is a classic pitch angle.

```python
schema = await evaluate('''
(function(){
  return Array.from(document.querySelectorAll('script[type="application/ld+json"]')).map(s => {
    try { const d = JSON.parse(s.textContent); return Array.isArray(d) ? d.map(x => x['@type']) : d['@type'] || d['@graph']?.map(x => x['@type']); }
    catch(e){ return 'INVALID JSON-LD'; }
  });
})()
''')
print(f'Schema.org types: {schema}')
```

Look for `LocalBusiness` (or subtypes like `Dentist`, `Restaurant`), `openingHoursSpecification`, `aggregateRating`. Report what's present and what's missing. For multi-location businesses, each location needs its own markup/page.

### Step 6 — Contact data / NAP extraction

Get NAP (Name, Address, Phone) from the legally required Impressum or the contact page — the most reliable sources on German sites.

```python
links = await evaluate('''
(function(){
  return Array.from(document.querySelectorAll('a')).map(a => ({text: a.textContent.trim(), href: a.href}))
    .filter(l => /impressum|kontakt|contact|imprint|legal|colofon/i.test(l.text + ' ' + l.href));
})()
''')
print(links)
```

Navigate to the Impressum, then extract:

```python
await navigate(impressum_url)
text_content = await evaluate('document.body.innerText')

import re
emails = list(set(re.findall(r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}', text_content)))
phones = list(set(re.findall(r'(?:\+49|0)[\d\s()\-/]{6,}', text_content)))
addresses = re.findall(r'[A-ZÄÖÜ][\wäöüß.\- ]+\s\d+[a-z]?\s*,?\s*\d{5}\s+[A-ZÄÖÜ][\wäöüß\- ]+', text_content)
vat_ids = re.findall(r'DE\s?\d{9}', text_content)
print(f'Emails: {emails}\nPhones: {phones}\nAddress: {addresses}\nUSt-IdNr: {vat_ids}')
```

Also grab social profile links:

```python
socials = await evaluate('''
(function(){
  const hosts = ['facebook.com','instagram.com','linkedin.com','youtube.com','tiktok.com','x.com','twitter.com','xing.com'];
  return [...new Set(Array.from(document.querySelectorAll('a[href]'))
    .map(a => a.href).filter(h => hosts.some(d => h.includes(d))))];
})()
''')
print(f'Social profiles: {socials}')
```

**Filter platform placeholders immediately:** links like `twitter.com/Wix`, `instagram.com/wix`, `facebook.com/jimdo` are builder defaults, not the business's profiles. Flag them as a finding (top demonstrable error) and treat the business as having NO linked profiles.

Watch for **platform-owned contact data**: if the only email is e.g. info@lieferando.de, the business has no own digital contact channel — note it as a finding.

Keep extraction limited to business contact data that the company itself publishes (Impressum, contact page, footer). Don't compile private/personal data about individuals.

### Step 7 — Conversion elements + link integrity

What can a visitor actually *do*?

```python
conv = await evaluate('''
(function(){
  const html = document.documentElement.outerHTML.toLowerCase();
  const txt = document.body.innerText.toLowerCase();
  return {
    forms: document.querySelectorAll('form').length,
    telLinks: document.querySelectorAll('a[href^="tel:"]').length,
    mailtoLinks: document.querySelectorAll('a[href^="mailto:"]').length,
    whatsapp: html.includes('wa.me') || html.includes('api.whatsapp.com'),
    onlineBooking: /termin|buchen|booking|reservier|calendly|doctolib|jameda|treatwell|opentable|resmio/.test(html),
    openingHours: /öffnungszeiten|opening hours/.test(txt),
    googleMapsEmbed: html.includes('google.com/maps') || html.includes('maps.googleapis')
  };
})()
''')
import json; print(json.dumps(conv, indent=2))
```

**Link integrity check** — counting tel:/mailto: links is not enough; verify they point where they claim. Template placeholders that were never replaced are common and devastating on mobile:

```python
contact_links = await evaluate('''
(function(){
  return Array.from(document.querySelectorAll('a[href^="tel:"], a[href^="mailto:"]'))
    .map(a => ({display: a.textContent.trim(), href: a.getAttribute('href')}));
})()
''')

import re
for l in contact_links:
    href_norm = re.sub(r'[^0-9a-z@.]', '', l['href'].replace('tel:','').replace('mailto:','').lower())
    disp_norm = re.sub(r'[^0-9a-z@.]', '', l['display'].lower())
    placeholder = any(p in l['href'].lower() for p in ['domain.com','example','987654321','mustermann'])
    mismatch = disp_norm and href_norm and disp_norm not in href_norm and href_norm not in disp_norm
    if placeholder or mismatch:
        print(f'⚠️ BROKEN/PLACEHOLDER LINK: displays "{l["display"]}" but links to "{l["href"]}"')
```

Cross-check tel:/mailto: targets against the Impressum data from step 6. A footer call button that dials a placeholder number is a top-tier, instantly demonstrable talking point.

### Step 8 — Social media activity audit (MANDATORY)

This is Klicklocal's home turf — never skip it. For each real profile found in step 6 (Instagram, Facebook, TikTok), visit the public profile page and record:

```python
# Per profile — navigate and extract what is publicly visible without login
await navigate('https://www.instagram.com/<handle>/')
profile_text = await evaluate('document.body.innerText')
# Record manually from the rendered page / text:
# - follower count
# - post count
# - date of the most recent post (open the first post if needed)
# - posting frequency estimate (dates of the last 3-6 posts)
# - content formats visible (Reels, Stories highlights, plain photos)
```

If a login wall blocks the profile, fall back to one web search: `"<business name>" instagram` — search snippets usually reveal follower count and recency. If nothing is verifiable, record "nicht verifizierbar" and award half points.

If NO real profiles are linked on the website, run one web search for `"<business name>" <city> instagram|facebook` to check whether unlinked profiles exist — an existing but unlinked profile is its own finding.

**Record per channel:** exists? · linked from website? · followers · last post date · posts/week · formats used. The killer pitch line lives here: *"Ihr letzter Instagram-Post ist vom [Datum] — Ihr Wettbewerber [Name] postet [n]×/Woche."*

### Step 9 — Google Business Profile & local visibility (MANDATORY)

For a local business, the Google profile often matters more than the website. Minimum version = one web search:

```
web_search('"<business name>" <city>')
web_search('<Branche> <city/district>')   # e.g. "türkisches restaurant augsburg"
```

Record:
- Does a Google Business Profile exist? Claimed (owner responses, posts) or unclaimed?
- Rating and review count.
- Does the lead appear in the Maps-Pack / top organic results for the category search? At which position?
- Top 2–3 competitors from the category search: **name, rating, review count** — these named figures feed the Gesprächsaufhänger and the Marktpotenzial section.

If search tooling is genuinely unavailable, say so in the *internal notes only* and award half points in the Google-Sichtbarkeit category — never write "aus Budgetgründen ausgelassen" in the report body.

## Step 10 — Scoring (X/100)

Score the site against this fixed rubric so results are reproducible across analysts and runs. Award points per criterion based on the data from steps 1–9; if something couldn't be verified, give half points and mark it "nicht verifizierbar" rather than guessing.

| Kategorie | Max | Kriterien (Punkte) |
|---|---|---|
| **Technik & Infrastruktur** | 10 | Eigene Domain erreichbar (3) · HTTPS (2) · Mobile/Viewport ok (3) · Eigenes CMS / Kontrolle über die Site (2) |
| **SEO** | 20 | Title gepflegt & lokal (4) · Meta Description gepflegt & lokal (4) · Saubere H1-Struktur (3) · Schema.org LocalBusiness (4) · Alt-Texte überwiegend vorhanden (2) · lang/canonical/OG gepflegt (3) |
| **Content & Vertrauen** | 10 | Vollständiges Impressum mit eigenen Kontaktdaten (4) · Öffnungszeiten online (2) · Aktuelle Inhalte / News (2) · Über-uns / Team / Story (2) |
| **Conversion** | 20 | Funktionierender tel:-Link (5) · Kontaktformular oder funktionierende mailto (4) · Online-Buchung/Reservierung/Bestellung (6) · Maps-Einbindung / Anfahrt (2) · Klare CTAs (3) |
| **Social-Media-Aktivität** | 15 | Echte Profile vorhanden & von der Website verlinkt (3) · Letzter Post < 30 Tage (4) · Frequenz ≥ 1 Post/Woche (4) · Zeitgemäße Formate (Reels/Stories) (2) · Follower-Basis zur Betriebsgröße passend (2) |
| **Google-Sichtbarkeit** | 10 | Google Business Profile vorhanden & gepflegt (3) · Bewertungszahl konkurrenzfähig (3) · Rating ≥ 4,3 (2) · Im Maps-Pack/Top-Ergebnissen sichtbar (2) |
| **Marketing-Reife** | 10 | Analytics/GTM installiert (4) · Werbe-Pixel (Meta/Google Ads) (3) · Consent-Tool (3) |
| **Eigenständigkeit** | 5 | Eigene E-Mail-Domain (2) · Eigene Telefonnummer online (1) · Nicht primär plattformabhängig — eigene Kundendaten & Direktkanal (2) |

Score bands for the verdict line:
- **0–39 — Kritisch**: digital faktisch nicht handlungsfähig; Neuaufbau-Pitch.
- **40–59 — Ausbaufähig**: Fundament vorhanden, klare Lücken; Optimierungs-Pitch.
- **60–79 — Solide**: gut aufgestellt, gezielte Hebel; Feinschliff-/Wachstums-Pitch.
- **80–100 — Stark**: wenig Quick-Wins; nur pitchen, wenn ein echter Hebel existiert.

Always show the per-category subscores in the report, not just the total — the gaps tell the sales story.

## Step 11 — Local market potential & growth estimate

Estimate, don't fabricate. Every number in this section must come with its assumption visible. The goal is a defensible range the salesperson can stand behind in a meeting — a single confident revenue figure with no basis will blow up in the follow-up conversation.

**11a. Market context (MANDATORY — uses step 9 data).** Summarize in 3–4 sentences: market density, the lead's current visibility (Maps-Pack position, rating/review count), and the gap to the local top — **naming the top competitors with their review counts**. Example: *"Bei 'türkisches Restaurant Augsburg' führen X (4,6★, 312 Bew.) und Y (4,5★, 209 Bew.); Erciyes erscheint nicht in den Top-Ergebnissen."*

**11b. Improvement levers.** From the score gaps, list the 3–5 fixes with the biggest expected impact (e.g. broken tel: link, missing LocalBusiness schema, no booking tool, dead Instagram). For each, note the mechanism: more visibility (SEO/GBP fixes), more conversions from existing traffic (conversion fixes), more reach & retention (social media activity), or recovered margin (platform independence).

**11c. Growth estimate (3 and 6 months).** Build the estimate bottom-up and show the formula:

```
Zusätzliche Kunden/Monat × Ø Bon/Auftragswert × Monate = Umsatzpotenzial
```

- **Average order value (Ø Bon):** ask the user if known; otherwise use a stated industry benchmark (e.g. Gastro-Bon ~15–30 €, Friseur ~40–70 €, Handwerk-Auftrag ~500–3.000 €) and label it as an assumption.
- **Additional customers:** derive conservatively from the levers (e.g. "ein funktionierender Anruf-Link + Google-Profil-Optimierung bringt bei einem Café dieser Lage realistisch 1–3 zusätzliche Gäste/Tag").
- **3-month horizon:** only conversion fixes, Google-profile effects and social reactivation materialize (SEO is too slow). **6-month horizon:** add early SEO/content effects.
- Always give a **conservative–optimistic range** and a percentage growth rate relative to an assumed baseline.
- **The Annahmen line is REQUIRED in every report**, e.g.: "Annahmen: Ø Bon 20 € (Branchenwert), Baseline ~100 Gäste/Tag, Hebel: Anruf-Link + GBP + Social-Reaktivierung."
- Close the section with one line: *"Schätzung auf Basis öffentlich sichtbarer Daten und Branchenwerten — keine Garantie; präzisierbar mit echten Umsatz-/Trafficdaten des Kunden."*

## Step 12 — The deliverable

### Lead report (single site)

The report is **customer-facing**: a prospect may see it. No model/cost/runtime metadata, no internal caveats about skipped tooling, no rubric jargon in prose. ALWAYS use this template:

```markdown
# Lead-Analyse: [Business name] — [URL]

## Gesamtbewertung: XX/100 — [Band-Label]
| Kategorie | Punkte |
|---|---|
| Technik & Infrastruktur | x/10 |
| SEO | x/20 |
| Content & Vertrauen | x/10 |
| Conversion | x/20 |
| Social-Media-Aktivität | x/15 |
| Google-Sichtbarkeit | x/10 |
| Marketing-Reife | x/10 |
| Eigenständigkeit | x/5 |

## Stärken
- ...

## Schwächen & Probleme
- ... (broken links, platform dependency, missing basics — concrete and demonstrable)

## Social-Media-Audit
Pro Kanal: Status, letzter Post, Frequenz, Follower, Formate — und der Vergleich zum aktivsten lokalen Wettbewerber.

## Google-Sichtbarkeit & Wettbewerb
GBP-Status, Rating/Bewertungen, Maps-Pack-Position, Top-2–3-Wettbewerber namentlich mit Rating & Bewertungszahl.

## Verbesserungspotenziale (priorisiert)
1. [Quick Win] ...
2. ...

## So hilft Klicklocal
Map die 3–5 wichtigsten Findings auf konkrete Klicklocal-Leistungen, je eine Zeile:
- [Finding: totes Instagram] → KI-Content-Studio: fertige Posts & Reels, automatisch geplant — [n] Posts/Woche ohne Mehraufwand.
- [Finding: kein TikTok] → Reel-Studio: 15-Sekunden-Reels für Instagram & TikTok aus einem Guss.
- [Finding: kein Tracking] → Einrichtung von Pixel & Analytics als Teil des Onboardings — jede Kampagne messbar.
Nur Leistungen nennen, die Klicklocal real anbietet (Social-Posting Instagram/TikTok/Facebook, KI-Content-Generierung, Reel-Studio, Planung/Kalender). Website-Umbauten o. ä. als Partner-/Zusatzleistung kennzeichnen.

## SEO-Bewertung
Title/Description, Überschriften, Schema.org, lokale Keywords, Auffälligkeiten — plus das SEO-Teilergebnis (x/20) in einem Satz eingeordnet.

## Kontaktdaten (CRM)
Firma/Inhaber, Adresse, Telefon, E-Mail, USt-IdNr, Social, Öffnungszeiten

## Lokales Marktpotenzial
Marktdichte, aktuelle Sichtbarkeit, Lücke zur lokalen Spitze — mit namentlich genannten Wettbewerbern (aus Schritt 11a)

## Wachstumsprognose
| Horizont | Wachstum | Umsatzpotenzial |
|---|---|---|
| 3 Monate | +x–y % | ~A–B € |
| 6 Monate | +x–y % | ~C–D € |

Annahmen: ... (Ø Bon, Baseline, Hebel) — PFLICHTZEILE
*Schätzung — keine Garantie; präzisierbar mit echten Kundendaten.*

## Gesprächsaufhänger (3–5 konkrete Punkte)
1. ...
```

The "Gesprächsaufhänger" section translates technical findings into business language. Not "no JSON-LD found" but "Restaurant X zeigt Sterne und Öffnungszeiten direkt in Google — Ihre Seite noch nicht." At least one Aufhänger MUST come from the social audit and one from the named competitor comparison.

### Interne Notizen (separate block, after the report)

After the customer-facing report, output a clearly separated **"--- Interne Notizen (nicht für den Kunden) ---"** block containing: anything unverifiable and why, tooling limits hit, scoring judgment calls, suggested pre-call checks, and run metadata (model/duration/cost) if desired. This block must NOT be part of the PDF/customer deliverable.

### Competitor comparison (multiple sites)

Run steps 1–10 per site, storing results in a dict keyed by domain. Produce one comparison table — rows: total score plus the eight category subscores, CMS, tracking, schema.org, conversion elements, social activity; columns: one per site — followed by a short strengths/weaknesses verdict per site and a ranking. If the user has a client among the sites, frame everything relative to that client: where do they lose points versus competitors, and what are the top 3 actions to close the gap. Run step 11 (potential/growth) only for the client, not for competitors.

## Tips

- Reuse helper functions across sites — define an `analyze(url)` wrapper once when comparing multiple sites.
- A cookie banner can overlay content; the HTML-based checks still work, but for screenshots or text extraction, dismiss the banner first if needed.
- For long pages use `await scroll(down=True)` and re-extract to capture below-fold content.
- If a check errors on an unusual site, degrade gracefully: report "nicht verifizierbar", award half points in scoring, and continue — but mandatory steps 8/9/11a may only be downgraded to their minimum version, never skipped.
- Report findings honestly — if a site is already well set up, say so and let the score reflect it; an over-pitched report damages credibility in the sales conversation. The same goes for growth numbers: ranges with visible assumptions win deals, invented precision loses them.
