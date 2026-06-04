<?php

namespace App\Services\SocialProviders\Facebook;

use App\Services\SocialProviders\AbstractSocialProviderSettingsService;

class FacebookProviderSettingsService extends AbstractSocialProviderSettingsService
{
    public function providerKey(): string
    {
        return 'facebook';
    }

    protected function configFile(): string
    {
        return 'facebook';
    }

    protected function updatableKeys(): array
    {
        return ['enabled', 'app_id', 'callback_url', 'api_version', 'scopes'];
    }

    protected function defaults(): array
    {
        return [
            'enabled' => (bool) config('facebook.enabled', false),
            'app_id' => config('facebook.app_id'),
            'app_secret' => config('facebook.app_secret'),
            'callback_url' => config('facebook.redirect_uri'),
            'api_version' => config('facebook.api_version'),
            'scopes' => config('facebook.scopes', []),
        ];
    }

    protected function adminMeta(): array
    {
        return [
            'name' => 'Facebook',
            'description' => 'Facebook API-Einstellungen für Seiten-Verbindungen und Publishing.',
            'setup_title' => 'Facebook-App einrichten',
            'setup_description' => 'Erstelle eine Meta-App mit Facebook Login und Pages API. Trage App ID und App Secret ein.',
            'secret_env_key' => 'FACEBOOK_APP_SECRET',
            'setup_doc' => null,
            'before_save' => [
                'Callback-URL in der Meta-App whitelisten.',
                'Pages API und erforderliche Berechtigungen aktivieren.',
                'App Secret nur in der Server-.env setzen.',
            ],
            'usage_note' => 'Nach der Aktivierung können Nutzer Facebook-Seiten in ihrem Dashboard verbinden.',
        ];
    }
}
