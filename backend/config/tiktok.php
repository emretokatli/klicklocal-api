<?php

return [

    'enabled' => (bool) env('TIKTOK_ENABLED', false),

    'client_key' => env('TIKTOK_CLIENT_KEY'),

    'client_secret' => env('TIKTOK_CLIENT_SECRET'),

    'redirect_uri' => env(
        'TIKTOK_REDIRECT_URI',
        rtrim((string) env('APP_URL', ''), '/').'/api/v1/social-accounts/tiktok/callback',
    ),

    'frontend_redirect' => env(
        'TIKTOK_FRONTEND_REDIRECT',
        rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/').'/social-accounts',
    ),

    'api_version' => env('TIKTOK_API_VERSION', 'v2'),

    'oauth_authorize_url' => 'https://www.tiktok.com/v2/auth/authorize/',

    'oauth_token_url' => 'https://open.tiktokapis.com/v2/oauth/token/',

    'user_info_url' => 'https://open.tiktokapis.com/v2/user/info/',

    'scopes' => [
        'user.info.basic',
        'video.publish',
        'video.upload',
    ],

    'state_ttl_minutes' => (int) env('TIKTOK_STATE_TTL_MINUTES', 15),

];
