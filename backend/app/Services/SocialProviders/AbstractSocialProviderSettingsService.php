<?php

namespace App\Services\SocialProviders;

use Illuminate\Support\Facades\Cache;

abstract class AbstractSocialProviderSettingsService
{
    abstract public function providerKey(): string;

    abstract protected function configFile(): string;

    /**
     * @return list<string>
     */
    abstract protected function updatableKeys(): array;

    /**
     * @return array<string, mixed>
     */
    abstract protected function defaults(): array;

    /**
     * @return array<string, mixed>
     */
    abstract protected function adminMeta(): array;

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $cached = Cache::get($this->cacheKey(), []);

        return array_merge($this->defaults(), $cached);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(array $payload): array
    {
        $keys = $this->updatableKeys();
        $settings = array_merge($this->get(), array_intersect_key($payload, array_flip($keys)));

        $toCache = [];
        foreach ($keys as $key) {
            if ($key === 'enabled') {
                $toCache['enabled'] = (bool) ($settings['enabled'] ?? false);

                continue;
            }

            $toCache[$key] = $settings[$key] ?? null;
        }

        Cache::forever($this->cacheKey(), $toCache);

        return $this->get();
    }

    public function isEnabled(): bool
    {
        $settings = $this->get();

        return (bool) ($settings['enabled'] ?? false)
            && filled($settings['app_id'] ?? null)
            && filled($this->appSecret());
    }

    public function appId(): ?string
    {
        $id = $this->get()['app_id'] ?? null;

        return filled($id) ? (string) $id : null;
    }

    public function appSecret(): ?string
    {
        $secret = $this->get()['app_secret'] ?? null;

        return filled($secret) ? (string) $secret : null;
    }

    public function redirectUri(): string
    {
        $custom = $this->get()['callback_url'] ?? null;
        if (filled($custom)) {
            return (string) $custom;
        }

        return (string) config("{$this->configFile()}.redirect_uri", '');
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        $settings = $this->get();
        $scopes = $settings['scopes'] ?? config("{$this->configFile()}.scopes", []);

        return is_array($scopes) ? array_values($scopes) : [];
    }

    public function apiVersion(): string
    {
        $settings = $this->get();
        $version = $settings['api_version'] ?? config("{$this->configFile()}.api_version", '');

        return (string) $version;
    }

    /**
     * @return array<string, mixed>
     */
    public function adminView(): array
    {
        $settings = $this->get();

        return array_merge($this->adminMeta(), [
            'provider' => $this->providerKey(),
            'enabled' => (bool) ($settings['enabled'] ?? false),
            'app_id' => $settings['app_id'] ?? null,
            'callback_url' => $this->redirectUri(),
            'configured' => $this->isEnabled(),
            'has_app_secret' => filled($this->appSecret()),
            'default_callback_url' => config("{$this->configFile()}.redirect_uri"),
            'scopes' => $this->scopes(),
            'api_version' => $this->apiVersion(),
            'status' => $this->resolveStatus(),
        ]);
    }

    protected function resolveStatus(): string
    {
        if ($this->isEnabled()) {
            return 'ready';
        }

        return $this->hasPartialConfig() ? 'setup' : 'disabled';
    }

    protected function hasPartialConfig(): bool
    {
        $settings = $this->get();

        return (bool) ($settings['enabled'] ?? false)
            || filled($settings['app_id'] ?? null)
            || filled($settings['callback_url'] ?? null);
    }

    protected function cacheKey(): string
    {
        return 'platform.social_providers.'.$this->providerKey();
    }
}
