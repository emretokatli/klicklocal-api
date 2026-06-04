<?php

namespace App\Services\SocialProviders\Instagram\DTOs;

use App\Enums\SocialAccountStatus;
use App\Models\SocialAccount;

readonly class InstagramAccountDTO
{
    public function __construct(
        public int $id,
        public int $workspaceId,
        public string $provider,
        public string $providerAccountId,
        public ?string $accountName,
        public ?string $username,
        public SocialAccountStatus $status,
        public ?string $tokenExpiresAt,
        public bool $tokenExpired,
        public ?array $metadata = null,
    ) {}

    public static function fromModel(SocialAccount $account): self
    {
        return new self(
            id: $account->id,
            workspaceId: $account->workspace_id,
            provider: $account->provider,
            providerAccountId: $account->provider_account_id,
            accountName: $account->account_name,
            username: $account->username,
            status: $account->status instanceof SocialAccountStatus
                ? $account->status
                : SocialAccountStatus::from((string) $account->status),
            tokenExpiresAt: $account->token_expires_at?->toIso8601String(),
            tokenExpired: $account->isTokenExpired(),
            metadata: $account->metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspaceId,
            'provider' => $this->provider,
            'provider_account_id' => $this->providerAccountId,
            'account_name' => $this->accountName,
            'username' => $this->username,
            'status' => $this->status->value,
            'token_expires_at' => $this->tokenExpiresAt,
            'token_expired' => $this->tokenExpired,
            'metadata' => $this->metadata,
        ];
    }
}
