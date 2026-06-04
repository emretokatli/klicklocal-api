<?php

namespace App\Services\SocialProviders\Instagram;

use App\Services\SocialProviders\AbstractSocialProviderSettingsService;

class InstagramProviderSettingsService extends AbstractSocialProviderSettingsService
{
    public function providerKey(): string
    {
        return 'instagram';
    }

    protected function configFile(): string
    {
        return 'instagram';
    }

    protected function updatableKeys(): array
    {
        return ['enabled', 'app_id', 'callback_url', 'api_version', 'scopes'];
    }

    protected function defaults(): array
    {
        return [
            'enabled' => (bool) config('instagram.enabled', false),
            'app_id' => config('instagram.app_id'),
            'app_secret' => config('instagram.app_secret'),
            'callback_url' => config('instagram.redirect_uri'),
            'api_version' => config('instagram.api_version'),
            'scopes' => config('instagram.scopes', []),
        ];
    }

    protected function adminMeta(): array
    {
        return [
            'name' => 'Instagram',
            'description' => 'Instagram Graph API für Business- und Creator-Profile.',
            'setup_title' => 'Instagram-App einrichten',
            'setup_description' => 'Erstelle eine Meta-App mit Instagram Graph API-Zugang. Trage App ID und App Secret ein.',
            'secret_env_key' => 'INSTAGRAM_APP_SECRET',
            'setup_doc' => 'docs/META-INSTAGRAM-SETUP.md',
            'before_save' => [
                'Instagram App ID aus Meta → Instagram → API setup with Instagram login verwenden.',
                'Profil mit Facebook-Seite verknüpfen.',
                'Callback-URL in der Meta-App whitelisten.',
            ],
            'usage_note' => 'Nach der Aktivierung können Nutzer Instagram Business-Profile verbinden.',
        ];
    }
}
