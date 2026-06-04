<?php

namespace App\Services\SocialProviders\TikTok;

use App\Services\SocialProviders\AbstractSocialProviderSettingsService;

class TikTokProviderSettingsService extends AbstractSocialProviderSettingsService
{
    public function providerKey(): string
    {
        return 'tiktok';
    }

    protected function configFile(): string
    {
        return 'tiktok';
    }

    protected function updatableKeys(): array
    {
        return ['enabled', 'app_id', 'callback_url', 'api_version', 'scopes'];
    }

    protected function defaults(): array
    {
        return [
            'enabled' => (bool) config('tiktok.enabled', false),
            'app_id' => config('tiktok.client_key'),
            'app_secret' => config('tiktok.client_secret'),
            'callback_url' => config('tiktok.redirect_uri'),
            'api_version' => config('tiktok.api_version'),
            'scopes' => config('tiktok.scopes', []),
        ];
    }

    protected function adminMeta(): array
    {
        return [
            'name' => 'TikTok',
            'description' => 'TikTok API-Einstellungen für Profil-Verbindungen und zukünftiges Publishing.',
            'setup_title' => 'TikTok-Developer-App einrichten',
            'setup_description' => 'Erstelle eine TikTok-Developer-App und trage Client Key und Client Secret ein.',
            'secret_env_key' => 'TIKTOK_CLIENT_SECRET',
            'setup_doc' => null,
            'before_save' => [
                'Login Kit und Content Posting in der Developer-App aktivieren.',
                'Genehmigte Scopes mit den unten eingetragenen Berechtigungen abgleichen.',
                'Callback-URL exakt in der TikTok-App hinterlegen.',
            ],
            'usage_note' => 'Nach der Aktivierung können Nutzer TikTok-Profile über OAuth verbinden.',
        ];
    }
}
