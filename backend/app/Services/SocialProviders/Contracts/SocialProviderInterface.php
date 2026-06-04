<?php

namespace App\Services\SocialProviders\Contracts;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;

interface SocialProviderInterface
{
    public function platform(): string;

    public function account(): SocialAccount;

    public function publish(Post $post): PublishResponseDTO;

    public function validateAccount(): bool;

    public function refreshToken(): SocialAccount;

    public function supports(string $capability): bool;

    /**
     * @return list<string>
     */
    public static function capabilities(): array;
}
