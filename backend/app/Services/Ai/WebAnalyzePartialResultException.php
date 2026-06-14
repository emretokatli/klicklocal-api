<?php

namespace App\Services\Ai;

use App\Services\Ai\DTOs\WebAnalyzeResultDTO;
use RuntimeException;

class WebAnalyzePartialResultException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly WebAnalyzeResultDTO $result,
    ) {
        parent::__construct($message);
    }
}
