<?php

namespace App\Services\SocialProviders;

use App\Enums\PostPlatformStatus;
use App\Enums\SocialAccountStatus;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\Workspace;
use App\Services\SocialProviders\Exceptions\SocialProviderException;
use App\Services\SocialProviders\Factory\SocialProviderFactory;
use App\Services\Usage\UsageTrackingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PostPlatformPublishingService
{
    public function __construct(
        private readonly SocialProviderFactory $providerFactory,
        private readonly UsageTrackingService $usageTracking,
    ) {}

    public function publishForPlatform(Post $post, PostPlatform $platform): bool
    {
        $account = $platform->socialAccount;
        $provider = $account->provider;

        try {
            $driver = $this->providerFactory->make($provider, $account);

            if (! $driver->validateAccount()) {
                // A failing validation is most often an expired/revoked token.
                $this->applyFailure(
                    $post,
                    $platform,
                    $provider,
                    'Social account failed validation. The connection may have expired.',
                    ['provider' => $provider],
                );

                return false;
            }

            $response = $driver->publish($post);

            if ($response->success) {
                $platform->markAsPublished($response);

                $workspace = Workspace::query()->find($post->workspace_id);
                if ($workspace !== null) {
                    $this->usageTracking->recordSocialApi($workspace, $provider);
                }

                Log::info('Post platform published via provider', [
                    'post_id' => $post->id,
                    'post_platform_id' => $platform->id,
                    'provider' => $provider,
                    'platform_post_id' => $response->platformPostId,
                ]);

                return true;
            }

            $this->applyFailure(
                $post,
                $platform,
                $provider,
                $response->message ?? 'Provider returned failure.',
                $response->rawResponse,
                $response->platformPostId,
            );

            return false;
        } catch (SocialProviderException $e) {
            $this->applyFailure($post, $platform, $provider, $e->getMessage());

            return false;
        } catch (Throwable $e) {
            // Unexpected error — treat as transient so the job retries it.
            $platform->markForRetry('Unerwarteter Fehler: '.Str::limit($e->getMessage(), 180));

            Log::error('Post platform publish exception (will retry)', [
                'post_id' => $post->id,
                'post_platform_id' => $platform->id,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Classify a failure per provider and either keep the platform Pending for
     * retry (transient) or mark it Failed (terminal).
     *
     * @param  array<string, mixed>|null  $rawResponse
     */
    private function applyFailure(
        Post $post,
        PostPlatform $platform,
        string $provider,
        string $rawMessage,
        ?array $rawResponse = null,
        ?string $platformPostId = null,
    ): void {
        [$message, $retryable, $expireAccount] = $this->classify($provider, $rawMessage);

        if ($expireAccount && $platform->socialAccount !== null) {
            $platform->socialAccount->update(['status' => SocialAccountStatus::Expired]);
        }

        if ($retryable) {
            $platform->markForRetry($message);

            Log::warning('Post platform publish failed (transient, will retry)', [
                'post_id' => $post->id,
                'post_platform_id' => $platform->id,
                'provider' => $provider,
                'message' => $message,
            ]);

            return;
        }

        $platform->markAsFailed($message, $rawResponse, $platformPostId);

        Log::warning('Post platform publish failed (terminal)', [
            'post_id' => $post->id,
            'post_platform_id' => $platform->id,
            'provider' => $provider,
            'message' => $message,
        ]);
    }

    /**
     * @return array{0: string, 1: bool, 2: bool}  [message, retryable, expireAccount]
     */
    private function classify(string $provider, string $rawMessage): array
    {
        $haystack = mb_strtolower($rawMessage);

        // Facebook / Instagram (Meta) token expiry — terminal, account needs reconnect.
        if ($this->matches($haystack, ['oauth', 'access token', 'token expired', 'session has expired', 'code 190', 'reconnect'])) {
            $label = $provider === 'facebook' ? 'Facebook' : 'Instagram';

            return ["{$label}-Zugriff abgelaufen. Bitte verbinde das Konto erneut.", false, true];
        }

        // Instagram media container still processing / timed out — transient.
        if ($provider === 'instagram'
            && $this->matches($haystack, ['processing took too long', 'container', 'media not ready', 'try again'])) {
            return ['Instagram verarbeitet das Medium noch. Es wird automatisch erneut versucht.', true, false];
        }

        // TikTok privacy / scope constraints — terminal (needs different settings).
        if ($provider === 'tiktok'
            && $this->matches($haystack, ['self_only', 'privacy', 'scope', 'unaudited'])) {
            return ['TikTok hat den Beitrag abgelehnt (Privatsphäre/Freigabe). Prüfe die Veröffentlichungsoptionen.', false, false];
        }

        // Generic transient signals (network, rate limit, timeout) — retry.
        if ($this->matches($haystack, ['network error', 'timeout', 'timed out', 'rate limit', 'temporarily', 'try again later', 'connection'])) {
            return ['Vorübergehender Fehler beim Veröffentlichen. Es wird automatisch erneut versucht.', true, false];
        }

        // Default: terminal with the original message.
        return [Str::limit($rawMessage, 240), false, false];
    }

    /**
     * @param  list<string>  $needles
     */
    private function matches(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
