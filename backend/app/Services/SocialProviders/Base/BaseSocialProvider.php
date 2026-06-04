<?php

namespace App\Services\SocialProviders\Base;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\SocialProviders\Contracts\SocialProviderInterface;
use App\Services\SocialProviders\DTOs\PublishResponseDTO;
use App\Services\SocialProviders\Exceptions\SocialProviderException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class BaseSocialProvider implements SocialProviderInterface
{
    public function __construct(
        protected readonly SocialAccount $account,
    ) {}

    abstract public function platform(): string;

    abstract public function publish(Post $post): PublishResponseDTO;

    public function account(): SocialAccount
    {
        return $this->account;
    }

    public function validateAccount(): bool
    {
        if (blank($this->account->provider) || $this->account->provider !== $this->platform()) {
            return false;
        }

        return filled($this->account->username)
            || filled($this->account->access_token)
            || filled($this->account->provider_account_id);
    }

    public function refreshToken(): SocialAccount
    {
        $this->logInfo('Token refresh simulated', [
            'account_id' => $this->account->id,
        ]);

        return $this->account;
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, static::capabilities(), true);
    }

    protected function ensureCapability(string $capability): void
    {
        if (! $this->supports($capability)) {
            throw SocialProviderException::capabilityNotSupported($this->platform(), $capability);
        }
    }

    protected function ensureValidAccount(): void
    {
        if (! $this->validateAccount()) {
            throw SocialProviderException::invalidAccount(
                $this->platform(),
                'Account is missing required credentials.',
            );
        }
    }

    protected function success(
        ?string $platformPostId = null,
        ?string $message = null,
        ?array $rawResponse = null,
    ): PublishResponseDTO {
        return PublishResponseDTO::success(
            platformPostId: $platformPostId ?? $this->generatePlatformPostId(),
            message: $message,
            rawResponse: $rawResponse ?? ['provider' => $this->platform(), 'simulated' => true],
        );
    }

    protected function failure(string $message, ?array $rawResponse = null): PublishResponseDTO
    {
        return PublishResponseDTO::failure($message, $rawResponse);
    }

    protected function generatePlatformPostId(): string
    {
        return $this->platform().'_'.Str::uuid()->toString();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        Log::info($message, array_merge([
            'provider' => $this->platform(),
            'social_account_id' => $this->account->id,
        ], $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logWarning(string $message, array $context = []): void
    {
        Log::warning($message, array_merge([
            'provider' => $this->platform(),
            'social_account_id' => $this->account->id,
        ], $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error($message, array_merge([
            'provider' => $this->platform(),
            'social_account_id' => $this->account->id,
        ], $context));
    }
}
