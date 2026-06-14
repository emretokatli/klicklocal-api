<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\WebAnalyzeClientInterface;
use App\Services\Ai\DTOs\WebAnalyzeResultDTO;

/**
 * Admin lead-report pipeline: runs the klicklocal-webanalyze agent skill and
 * returns a scored markdown report. Not to be confused with the onboarding
 * text analysis (WebsiteAnalysisService).
 */
class WebAnalyzeService
{
    public function __construct(
        private readonly WebAnalyzeClientInterface $client,
    ) {}

    public function analyze(string $website): WebAnalyzeResultDTO
    {
        return $this->client->analyze($website);
    }
}
