<?php

namespace App\Services\SocialProviders\Instagram\DTOs;

readonly class InstagramTokenDTO
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $accessToken,
        public string $userId,
        public ?string $tokenType = 'bearer',
        public ?int $expiresIn = null,
        public array $permissions = [],
        public bool $isLongLived = false,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromShortLivedResponse(array $payload): self
    {
        $row = $payload['data'][0] ?? $payload;

        $permissions = [];
        if (isset($row['permissions']) && is_string($row['permissions'])) {
            $permissions = array_map('trim', explode(',', $row['permissions']));
        }

        return new self(
            accessToken: (string) ($row['access_token'] ?? ''),
            userId: (string) ($row['user_id'] ?? ''),
            permissions: $permissions,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromLongLivedResponse(array $payload, string $userId): self
    {
        return new self(
            accessToken: (string) ($payload['access_token'] ?? ''),
            userId: $userId,
            tokenType: (string) ($payload['token_type'] ?? 'bearer'),
            expiresIn: isset($payload['expires_in']) ? (int) $payload['expires_in'] : null,
            isLongLived: true,
        );
    }
}
