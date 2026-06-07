<?php

namespace App\Services\SocialProviders\TikTok\DTOs;

readonly class TikTokTokenDTO
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public string $accessToken,
        public string $openId,
        public ?string $refreshToken = null,
        public ?int $expiresIn = null,
        public ?int $refreshExpiresIn = null,
        public ?string $tokenType = 'Bearer',
        public array $scopes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload): self
    {
        $scopes = [];
        if (isset($payload['scope']) && is_string($payload['scope'])) {
            $scopes = array_values(array_filter(array_map('trim', explode(',', $payload['scope']))));
        }

        return new self(
            accessToken: (string) ($payload['access_token'] ?? ''),
            openId: (string) ($payload['open_id'] ?? ''),
            refreshToken: isset($payload['refresh_token']) ? (string) $payload['refresh_token'] : null,
            expiresIn: isset($payload['expires_in']) ? (int) $payload['expires_in'] : null,
            refreshExpiresIn: isset($payload['refresh_expires_in']) ? (int) $payload['refresh_expires_in'] : null,
            tokenType: (string) ($payload['token_type'] ?? 'Bearer'),
            scopes: $scopes,
        );
    }
}
