<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\DTOs\WebAnalyzeResultDTO;

interface WebAnalyzeClientInterface
{
    public function analyze(string $website): WebAnalyzeResultDTO;
}
