<?php

namespace App\Services\SocialProviders\Facebook\DTOs;

readonly class FacebookPageDTO
{
    /**
     * @param  list<string>  $tasks
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $accessToken,
        public ?string $category = null,
        public array $tasks = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload): self
    {
        $tasks = [];
        if (isset($payload['tasks']) && is_array($payload['tasks'])) {
            $tasks = array_values(array_map('strval', $payload['tasks']));
        }

        return new self(
            id: (string) ($payload['id'] ?? ''),
            name: (string) ($payload['name'] ?? ''),
            accessToken: (string) ($payload['access_token'] ?? ''),
            category: isset($payload['category']) ? (string) $payload['category'] : null,
            tasks: $tasks,
        );
    }
}
