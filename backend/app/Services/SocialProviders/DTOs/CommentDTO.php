<?php

namespace App\Services\SocialProviders\DTOs;

use Illuminate\Support\Carbon;

readonly class CommentDTO
{
    /**
     * @param  array<string, mixed>|null  $raw
     */
    public function __construct(
        public string $externalId,
        public string $author,
        public string $text,
        public ?Carbon $commentedAt = null,
        public ?array $raw = null,
    ) {}
}
