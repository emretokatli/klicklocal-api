<?php

namespace App\Services\SocialProviders\Instagram;

use App\Models\OAuthState;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SocialProviders\Instagram\DTOs\InstagramTokenDTO;
use App\Services\SocialProviders\Instagram\DTOs\InstagramUserDTO;
use App\Services\SocialProviders\Instagram\Exceptions\InstagramOAuthException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InstagramOAuthService
{
    public function __construct(
        private readonly InstagramProviderSettingsService $settings,
    ) {}

    public function ensureConfigured(): void
    {
        if (! $this->settings->isEnabled()) {
            throw InstagramOAuthException::providerDisabled();
        }

        if (! $this->settings->appId()) {
            throw InstagramOAuthException::missingConfiguration('app_id');
        }

        if (! $this->settings->appSecret()) {
            throw InstagramOAuthException::missingConfiguration('app_secret');
        }

        if (! filled($this->settings->redirectUri())) {
            throw InstagramOAuthException::missingConfiguration('redirect_uri');
        }
    }

    public function createState(Workspace $workspace, User $user): OAuthState
    {
        $this->ensureConfigured();

        $state = Str::random(48);
        $ttl = (int) config('instagram.state_ttl_minutes', 15);

        Log::info('Instagram OAuth state created', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);

        return OAuthState::query()->create([
            'state' => $state,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'provider' => 'instagram',
            'expires_at' => now()->addMinutes($ttl),
        ]);
    }

    public function buildAuthorizationUrl(OAuthState $oauthState): string
    {
        $scopes = implode(',', $this->settings->scopes());

        $query = http_build_query([
            'client_id' => $this->settings->appId(),
            'redirect_uri' => $this->settings->redirectUri(),
            'response_type' => 'code',
            'scope' => $scopes,
            'state' => $oauthState->state,
        ]);

        $url = config('instagram.oauth_authorize_url').'?'.$query;

        Log::info('Instagram OAuth authorization URL generated', [
            'workspace_id' => $oauthState->workspace_id,
            'user_id' => $oauthState->user_id,
        ]);

        return $url;
    }

    public function resolveState(string $state): OAuthState
    {
        $record = OAuthState::query()
            ->where('state', $state)
            ->where('provider', 'instagram')
            ->first();

        if ($record === null) {
            Log::warning('Instagram OAuth invalid state', ['state' => $state]);
            throw InstagramOAuthException::invalidState();
        }

        if ($record->isExpired()) {
            $record->delete();
            Log::warning('Instagram OAuth expired state', ['state' => $state]);
            throw InstagramOAuthException::expiredState();
        }

        return $record;
    }

    public function consumeState(OAuthState $oauthState): void
    {
        $oauthState->delete();
    }

    public function exchangeAuthorizationCode(string $code): InstagramTokenDTO
    {
        $this->ensureConfigured();

        Log::info('Instagram OAuth exchanging authorization code');

        try {
            $response = Http::asForm()
                ->timeout(30)
                ->post(config('instagram.oauth_token_url'), [
                    'client_id' => $this->settings->appId(),
                    'client_secret' => $this->settings->appSecret(),
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $this->settings->redirectUri(),
                    'code' => $code,
                ]);
        } catch (RequestException $e) {
            throw InstagramOAuthException::networkFailure($e->getMessage());
        }

        if (! $response->successful()) {
            $message = (string) ($response->json('error_message') ?? $response->body());
            Log::error('Instagram OAuth token exchange failed', ['response' => $response->json()]);
            throw InstagramOAuthException::tokenExchangeFailed($message);
        }

        $shortLived = InstagramTokenDTO::fromShortLivedResponse($response->json());

        return $this->exchangeLongLivedToken($shortLived);
    }

    public function exchangeLongLivedToken(InstagramTokenDTO $shortLived): InstagramTokenDTO
    {
        $url = rtrim(config('instagram.graph_base_url'), '/').'/access_token';

        try {
            $response = Http::timeout(30)->get($url, [
                'grant_type' => 'ig_exchange_token',
                'client_secret' => $this->settings->appSecret(),
                'access_token' => $shortLived->accessToken,
            ]);
        } catch (RequestException $e) {
            throw InstagramOAuthException::networkFailure($e->getMessage());
        }

        if (! $response->successful()) {
            $message = (string) ($response->json('error.message') ?? $response->body());
            Log::error('Instagram long-lived token exchange failed', ['response' => $response->json()]);
            throw InstagramOAuthException::tokenExchangeFailed($message);
        }

        Log::info('Instagram long-lived token obtained', ['user_id' => $shortLived->userId]);

        return InstagramTokenDTO::fromLongLivedResponse($response->json(), $shortLived->userId);
    }

    public function refreshLongLivedToken(string $accessToken): InstagramTokenDTO
    {
        $url = rtrim(config('instagram.graph_base_url'), '/').'/refresh_access_token';

        try {
            $response = Http::timeout(30)->get($url, [
                'grant_type' => 'ig_refresh_token',
                'access_token' => $accessToken,
            ]);
        } catch (RequestException $e) {
            throw InstagramOAuthException::networkFailure($e->getMessage());
        }

        if (! $response->successful()) {
            $message = (string) ($response->json('error.message') ?? $response->body());
            throw InstagramOAuthException::tokenExchangeFailed($message);
        }

        $userId = $this->fetchUserProfile($accessToken)->id;

        return InstagramTokenDTO::fromLongLivedResponse($response->json(), $userId);
    }

    public function fetchUserProfile(string $accessToken): InstagramUserDTO
    {
        $version = $this->settings->apiVersion() ?: config('instagram.api_version', 'v21.0');
        $url = rtrim(config('instagram.graph_base_url'), '/')."/{$version}/me";

        try {
            $response = Http::timeout(30)->get($url, [
                'fields' => 'id,user_id,username,name,account_type',
                'access_token' => $accessToken,
            ]);
        } catch (RequestException $e) {
            throw InstagramOAuthException::networkFailure($e->getMessage());
        }

        if (! $response->successful()) {
            $message = (string) ($response->json('error.message') ?? $response->body());
            throw InstagramOAuthException::apiError($message, $response->status());
        }

        return InstagramUserDTO::fromGraph($response->json());
    }

    public function validateAccessToken(string $accessToken): bool
    {
        try {
            $this->fetchUserProfile($accessToken);

            return true;
        } catch (InstagramOAuthException) {
            return false;
        }
    }

    public function frontendRedirectUrl(string $status, ?string $message = null): string
    {
        $base = rtrim((string) config('instagram.frontend_redirect'), '/');
        $query = http_build_query(array_filter([
            'instagram' => $status,
            'message' => $message,
        ]));

        return $base.'?'.$query;
    }
}
