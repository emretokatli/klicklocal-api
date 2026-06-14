<?php

namespace App\Services\Ai\DTOs;

class SuggestedReplyDTO
{
    public function __construct(
        public readonly string $replyText,
        public readonly string $model,
        public readonly int $tokensUsed,
    ) {}
}
