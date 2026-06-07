<?php

namespace App\Services\SocialProviders\TikTok\DTOs;

readonly class TikTokUserDTO
{
    public function __construct(
        public string $openId,
        public ?string $unionId = null,
        public ?string $displayName = null,
        public ?string $avatarUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload): self
    {
        $user = $payload['data']['user'] ?? $payload['user'] ?? $payload;

        return new self(
            openId: (string) ($user['open_id'] ?? ''),
            unionId: isset($user['union_id']) ? (string) $user['union_id'] : null,
            displayName: isset($user['display_name']) ? (string) $user['display_name'] : null,
            avatarUrl: isset($user['avatar_url']) ? (string) $user['avatar_url'] : null,
        );
    }
}
