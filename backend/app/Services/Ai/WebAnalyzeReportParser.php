<?php

namespace App\Services\Ai;

class WebAnalyzeReportParser
{
    /**
     * @return array{0: int|null, 1: string|null}
     */
    public static function parseScore(string $markdown): array
    {
        if (preg_match('/Gesamtbewertung:\s*(\d+)\/100\s*[—\-–]\s*(.+)/iu', $markdown, $matches) !== 1) {
            return [null, null];
        }

        $score = (int) $matches[1];
        $band = trim($matches[2]);

        return [$score, $band !== '' ? $band : null];
    }
}
