<?php

namespace App\Services\SocialProviders\Facebook\DTOs;

readonly class FacebookUserDTO
{
    public function __construct(
        public string $id,
        public ?string $name = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload): self
    {
        return new self(
            id: (string) ($payload['id'] ?? ''),
            name: isset($payload['name']) ? (string) $payload['name'] : null,
        );
    }
}
