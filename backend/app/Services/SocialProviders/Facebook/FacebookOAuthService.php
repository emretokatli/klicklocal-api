<?php

namespace App\Services\SocialProviders\Facebook;

use App\Models\OAuthState;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SocialProviders\Facebook\DTOs\FacebookPageDTO;
use App\Services\SocialProviders\Facebook\DTOs\FacebookTokenDTO;
use App\Services\SocialProviders\Facebook\DTOs\FacebookUserDTO;
use App\Services\SocialProviders\Facebook\Exceptions\FacebookOAuthException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FacebookOAuthService
{
    public function __construct(
        private readonly FacebookProviderSettingsService $settings,
    ) {}

    public function ensureConfigured(): void
    {
        if (! $this->settings->isEnabled()) {
            throw FacebookOAuthException::providerDisabled();
        }

        if (! $this->settings->appId()) {
            throw FacebookOAuthException::missingConfiguration('app_id');
        }

        if (! $this->settings->appSecret()) {
            throw FacebookOAuthException::missingConfiguration('app_secret');
        }

        if (! filled($this->settings->redirectUri())) {
            throw FacebookOAuthException::missingConfiguration('redirect_uri');
        }
    }

    public function createState(Workspace $workspace, User $user): OAuthState
    {
        $this->ensureConfigured();

        $state = Str::random(48);
        $ttl = (int) config('facebook.state_ttl_minutes', 15);

        Log::info('Facebook OAuth state created', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);

        return OAuthState::query()->create([
            'state' => $state,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'provider' => 'facebook',
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

        $url = config('facebook.oauth_authorize_url').'?'.$query;

        Log::info('Facebook OAuth authorization URL generated', [
            'workspace_id' => $oauthState->workspace_id,
            'user_id' => $oauthState->user_id,
        ]);

        return $url;
    }

    public function resolveState(string $state): OAuthState
    {
        $record = OAuthState::query()
            ->where('state', $state)
            ->where('provider', 'facebook')
            ->first();

        if ($record === null) {
            Log::warning('Facebook OAuth invalid state', ['state' => $state]);
            throw FacebookOAuthException::invalidState();
        }

        if ($record->isExpired()) {
            $record->delete();
            Log::warning('Facebook OAuth expired state', ['state' => $state]);
            throw FacebookOAuthException::expiredState();
        }

        return $record;
    }

    public function consumeState(OAuthState $oauthState): void
    {
        $oauthState->delete();
    }

    /**
     * Exchange the authorization code for a (short-lived) user access token.
     */
    public function exchangeAuthorizationCode(string $code): FacebookTokenDTO
    {
        $this->ensureConfigured();

        Log::info('Facebook OAuth exchanging authorization code');

        $url = $this->graphUrl('oauth/access_token');

        try {
            $response = Http::timeout(30)->get($url, [
                'client_id' => $this->settings->appId(),
                'client_secret' => $this->settings->appSecret(),
                'redirect_uri' => $this->settings->redirectUri(),
                'code' => $code,
            ]);
        } catch (RequestException $e) {
            throw FacebookOAuthException::networkFailure($e->getMessage());
        }

        if (! $response->successful() || $response->json('error')) {
            $message = (string) ($response->json('error.message') ?? $response->body());
            Log::error('Facebook OAuth token exchange failed', ['response' => $response->json()]);
            throw FacebookOAuthException::tokenExchangeFailed($message);
        }

        return FacebookTokenDTO::fromResponse($response->json());
    }

    /**
     * Exchange a short-lived user token for a long-lived user token.
     */
    public function exchangeLongLivedToken(FacebookTokenDTO $shortLived): FacebookTokenDTO
    {
        $url = $this->graphUrl('oauth/access_token');

        try {
            $response = Http::timeout(30)->get($url, [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $this->settings->appId(),
                'client_secret' => $this->settings->appSecret(),
                'fb_exchange_token' => $shortLived->accessToken,
            ]);
        } catch (RequestException $e) {
            throw FacebookOAuthException::networkFailure($e->getMessage());
        }

        if (! $response->successful() || $response->json('error')) {
            $message = (string) ($response->json('error.message') ?? $response->body());
            Log::error('Facebook long-lived token exchange failed', ['response' => $response->json()]);
            throw FacebookOAuthException::tokenExchangeFailed($message);
        }

        return FacebookTokenDTO::fromResponse($response->json());
    }

    public function fetchUserProfile(string $userAccessToken): FacebookUserDTO
    {
        $url = $this->graphUrl('me');

        try {
            $response = Http::timeout(30)->get($url, [
                'fields' => 'id,name',
                'access_token' => $userAccessToken,
            ]);
        } catch (RequestException $e) {
            throw FacebookOAuthException::networkFailure($e->getMessage());
        }

        if (! $response->successful() || $response->json('error')) {
            $message = (string) ($response->json('error.message') ?? $response->body());
            throw FacebookOAuthException::apiError($message, $response->status());
        }

        return FacebookUserDTO::fromResponse($response->json());
    }

    /**
     * Fetch the Pages the user manages, each with its own Page access token.
     * When the user token is long-lived, the Page tokens are long-lived too.
     *
     * @return list<FacebookPageDTO>
     */
    public function fetchPages(string $userAccessToken): array
    {
        $url = $this->graphUrl('me/accounts');

        try {
            $response = Http::timeout(30)->get($url, [
                'fields' => 'id,name,access_token,category,tasks',
                'access_token' => $userAccessToken,
            ]);
        } catch (RequestException $e) {
            throw FacebookOAuthException::networkFailure($e->getMessage());
        }

        if (! $response->successful() || $response->json('error')) {
            $message = (string) ($response->json('error.message') ?? $response->body());
            throw FacebookOAuthException::apiError($message, $response->status());
        }

        $pages = [];
        foreach ((array) $response->json('data', []) as $page) {
            if (! is_array($page)) {
                continue;
            }

            $dto = FacebookPageDTO::fromResponse($page);

            if ($dto->id !== '' && $dto->accessToken !== '') {
                $pages[] = $dto;
            }
        }

        if ($pages === []) {
            throw FacebookOAuthException::noPages();
        }

        return $pages;
    }

    public function validateAccessToken(string $accessToken): bool
    {
        $url = $this->graphUrl('me');

        try {
            $response = Http::timeout(30)->get($url, [
                'fields' => 'id',
                'access_token' => $accessToken,
            ]);
        } catch (RequestException) {
            return false;
        }

        return $response->successful() && ! $response->json('error');
    }

    public function frontendRedirectUrl(string $status, ?string $message = null): string
    {
        $base = rtrim((string) config('facebook.frontend_redirect'), '/');
        $query = http_build_query(array_filter([
            'facebook' => $status,
            'message' => $message,
        ]));

        return $base.'?'.$query;
    }

    private function graphUrl(string $path): string
    {
        $version = $this->settings->apiVersion() ?: config('facebook.api_version', 'v25.0');
        $base = rtrim((string) config('facebook.graph_base_url'), '/');

        return "{$base}/{$version}/".ltrim($path, '/');
    }
}
