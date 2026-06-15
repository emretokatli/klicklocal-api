<?php

namespace App\Services\SocialProviders\Facebook\DTOs;

readonly class FacebookTokenDTO
{
    public function __construct(
        public string $accessToken,
        public ?int $expiresIn = null,
        public ?string $tokenType = 'bearer',
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload): self
    {
        return new self(
            accessToken: (string) ($payload['access_token'] ?? ''),
            expiresIn: isset($payload['expires_in']) ? (int) $payload['expires_in'] : null,
            tokenType: (string) ($payload['token_type'] ?? 'bearer'),
        );
    }
}
