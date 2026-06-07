<?php

namespace App\Services\SocialProviders\TikTok;

use App\Models\OAuthState;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SocialProviders\TikTok\DTOs\TikTokTokenDTO;
use App\Services\SocialProviders\TikTok\DTOs\TikTokUserDTO;
use App\Services\SocialProviders\TikTok\Exceptions\TikTokOAuthException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TikTokOAuthService
{
    public function __construct(
        private readonly TikTokProviderSettingsService $settings,
    ) {}

    public function ensureConfigured(): void
    {
        if (! $this->settings->isEnabled()) {
            throw TikTokOAuthException::providerDisabled();
        }

        if (! $this->settings->appId()) {
            throw TikTokOAuthException::missingConfiguration('client_key');
        }

        if (! $this->settings->appSecret()) {
            throw TikTokOAuthException::missingConfiguration('client_secret');
        }

        if (! filled($this->settings->redirectUri())) {
            throw TikTokOAuthException::missingConfiguration('redirect_uri');
        }
    }

    public function createState(Workspace $workspace, User $user): OAuthState
    {
        $this->ensureConfigured();

        $state = Str::random(48);
        $ttl = (int) config('tiktok.state_ttl_minutes', 15);

        Log::info('TikTok OAuth state created', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);

        return OAuthState::query()->create([
            'state' => $state,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'provider' => 'tiktok',
            'expires_at' => now()->addMinutes($ttl),
        ]);
    }

    public function buildAuthorizationUrl(OAuthState $oauthState): string
    {
        $scopes = implode(',', $this->settings->scopes());

        $query = http_build_query([
            'client_key' => $this->settings->appId(),
            'redirect_uri' => $this->settings->redirectUri(),
            'response_type' => 'code',
            'scope' => $scopes,
            'state' => $oauthState->state,
        ]);

        $url = config('tiktok.oauth_authorize_url').'?'.$query;

        Log::info('TikTok OAuth authorization URL generated', [
            'workspace_id' => $oauthState->workspace_id,
            'user_id' => $oauthState->user_id,
        ]);

        return $url;
    }

    public function resolveState(string $state): OAuthState
    {
        $record = OAuthState::query()
            ->where('state', $state)
            ->where('provider', 'tiktok')
            ->first();

        if ($record === null) {
            Log::warning('TikTok OAuth invalid state', ['state' => $state]);
            throw TikTokOAuthException::invalidState();
        }

        if ($record->isExpired()) {
            $record->delete();
            Log::warning('TikTok OAuth expired state', ['state' => $state]);
            throw TikTokOAuthException::expiredState();
        }

        return $record;
    }

    public function consumeState(OAuthState $oauthState): void
    {
        $oauthState->delete();
    }

    public function exchangeAuthorizationCode(string $code): TikTokTokenDTO
    {
        $this->ensureConfigured();

        Log::info('TikTok OAuth exchanging authorization code');

        try {
            $response = Http::asForm()
                ->timeout(30)
                ->post(config('tiktok.oauth_token_url'), [
                    'client_key' => $this->settings->appId(),
                    'client_secret' => $this->settings->appSecret(),
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $this->settings->redirectUri(),
                    'code' => $code,
                ]);
        } catch (RequestException $e) {
            throw TikTokOAuthException::networkFailure($e->getMessage());
        }

        if (! $response->successful() || $response->json('error')) {
            $message = (string) ($response->json('error_description') ?? $response->json('error') ?? $response->body());
            Log::error('TikTok OAuth token exchange failed', ['response' => $response->json()]);
            throw TikTokOAuthException::tokenExchangeFailed($message);
        }

        return TikTokTokenDTO::fromResponse($response->json());
    }

    public function refreshAccessToken(string $refreshToken): TikTokTokenDTO
    {
        $this->ensureConfigured();

        try {
            $response = Http::asForm()
                ->timeout(30)
                ->post(config('tiktok.oauth_token_url'), [
                    'client_key' => $this->settings->appId(),
                    'client_secret' => $this->settings->appSecret(),
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);
        } catch (RequestException $e) {
            throw TikTokOAuthException::networkFailure($e->getMessage());
        }

        if (! $response->successful() || $response->json('error')) {
            $message = (string) ($response->json('error_description') ?? $response->json('error') ?? $response->body());
            Log::error('TikTok token refresh failed', ['response' => $response->json()]);
            throw TikTokOAuthException::tokenExchangeFailed($message);
        }

        Log::info('TikTok access token refreshed');

        return TikTokTokenDTO::fromResponse($response->json());
    }

    public function fetchUserProfile(string $accessToken): TikTokUserDTO
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->get(config('tiktok.user_info_url'), [
                    'fields' => 'open_id,union_id,avatar_url,display_name',
                ]);
        } catch (RequestException $e) {
            throw TikTokOAuthException::networkFailure($e->getMessage());
        }

        $errorCode = $response->json('error.code');
        if (! $response->successful() || ($errorCode !== null && $errorCode !== 'ok')) {
            $message = (string) ($response->json('error.message') ?? $response->body());
            throw TikTokOAuthException::apiError($message, $response->status());
        }

        return TikTokUserDTO::fromResponse($response->json());
    }

    public function validateAccessToken(string $accessToken): bool
    {
        try {
            $this->fetchUserProfile($accessToken);

            return true;
        } catch (TikTokOAuthException) {
            return false;
        }
    }

    public function frontendRedirectUrl(string $status, ?string $message = null): string
    {
        $base = rtrim((string) config('tiktok.frontend_redirect'), '/');
        $query = http_build_query(array_filter([
            'tiktok' => $status,
            'message' => $message,
        ]));

        return $base.'?'.$query;
    }
}
