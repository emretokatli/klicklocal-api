<?php

namespace App\Services\SocialProviders\Instagram\DTOs;

readonly class InstagramUserDTO
{
    public function __construct(
        public string $id,
        public ?string $username = null,
        public ?string $name = null,
        public ?string $accountType = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromGraph(array $payload): self
    {
        return new self(
            id: (string) ($payload['id'] ?? $payload['user_id'] ?? ''),
            username: isset($payload['username']) ? (string) $payload['username'] : null,
            name: isset($payload['name']) ? (string) $payload['name'] : null,
            accountType: isset($payload['account_type']) ? (string) $payload['account_type'] : null,
        );
    }
}
