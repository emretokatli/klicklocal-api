<?php

namespace App\Services\Ai\DTOs;

class GeneratedImageDTO
{
    public function __construct(
        public readonly string $imageUrl,
        public readonly string $model,
        public readonly string $revisedPrompt = '',
        public readonly bool $isFake = false,
    ) {}
}
