<?php

namespace Tests\Unit;

use App\Services\Ai\WebAnalyzeReportParser;
use PHPUnit\Framework\TestCase;

class WebAnalyzeReportParserTest extends TestCase
{
    public function test_parses_score_and_band_from_markdown(): void
    {
        $markdown = "# Lead-Analyse\n\n## Gesamtbewertung: 67/100 — Solide\n";

        [$score, $band] = WebAnalyzeReportParser::parseScore($markdown);

        $this->assertSame(67, $score);
        $this->assertSame('Solide', $band);
    }

    public function test_returns_null_when_score_missing(): void
    {
        [$score, $band] = WebAnalyzeReportParser::parseScore('# Report without score');

        $this->assertNull($score);
        $this->assertNull($band);
    }
}
