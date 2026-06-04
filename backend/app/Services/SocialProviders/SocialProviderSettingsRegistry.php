<?php

namespace App\Services\SocialProviders;

use App\Services\SocialProviders\Facebook\FacebookProviderSettingsService;
use App\Services\SocialProviders\Instagram\InstagramProviderSettingsService;
use App\Services\SocialProviders\TikTok\TikTokProviderSettingsService;

class SocialProviderSettingsRegistry
{
    public function __construct(
        private readonly FacebookProviderSettingsService $facebook,
        private readonly InstagramProviderSettingsService $instagram,
        private readonly TikTokProviderSettingsService $tiktok,
    ) {}

    /**
     * @return list<string>
     */
    public function supported(): array
    {
        return ['facebook', 'instagram', 'tiktok'];
    }

    public function resolve(string $provider): ?AbstractSocialProviderSettingsService
    {
        return match (strtolower($provider)) {
            'facebook' => $this->facebook,
            'instagram' => $this->instagram,
            'tiktok' => $this->tiktok,
            default => null,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function adminViews(): array
    {
        return array_map(
            fn (AbstractSocialProviderSettingsService $service) => $service->adminView(),
            $this->services(),
        );
    }

    /**
     * @return list<AbstractSocialProviderSettingsService>
     */
    private function services(): array
    {
        return [$this->facebook, $this->instagram, $this->tiktok];
    }
}
