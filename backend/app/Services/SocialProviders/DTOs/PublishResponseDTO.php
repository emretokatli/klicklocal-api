<?php

namespace App\Services\SocialProviders\DTOs;

readonly class PublishResponseDTO
{
    /**
     * @param  array<string, mixed>|null  $rawResponse
     */
    public function __construct(
        public bool $success,
        public ?string $platformPostId = null,
        public ?string $message = null,
        public ?array $rawResponse = null,
    ) {}

    public static function success(
        string $platformPostId,
        ?string $message = null,
        ?array $rawResponse = null,
    ): self {
        return new self(
            success: true,
            platformPostId: $platformPostId,
            message: $message ?? 'Published successfully.',
            rawResponse: $rawResponse,
        );
    }

    public static function failure(
        string $message,
        ?array $rawResponse = null,
    ): self {
        return new self(
            success: false,
            message: $message,
            rawResponse: $rawResponse,
        );
    }
}
