<?php

return [

    'enabled' => (bool) env('FACEBOOK_ENABLED', false),

    'app_id' => env('FACEBOOK_APP_ID'),

    'app_secret' => env('FACEBOOK_APP_SECRET'),

    'redirect_uri' => env(
        'FACEBOOK_REDIRECT_URI',
        rtrim((string) env('APP_URL', ''), '/').'/api/v1/social-accounts/facebook/callback',
    ),

    'frontend_redirect' => env(
        'FACEBOOK_FRONTEND_REDIRECT',
        rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/').'/social-accounts',
    ),

    'api_version' => env('FACEBOOK_API_VERSION', 'v25.0'),

    'scopes' => [
        'pages_read_engagement',
        'pages_manage_posts',
        'pages_show_list',
        'business_management',
    ],

    'state_ttl_minutes' => (int) env('FACEBOOK_STATE_TTL_MINUTES', 15),

];
