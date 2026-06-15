<?php

return [

    'enabled' => (bool) env('INSTAGRAM_ENABLED', false),

    'app_id' => env('INSTAGRAM_APP_ID'),

    'app_secret' => env('INSTAGRAM_APP_SECRET'),

    'redirect_uri' => env('INSTAGRAM_REDIRECT_URI'),

    'frontend_redirect' => env(
        'INSTAGRAM_FRONTEND_REDIRECT',
        rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/').'/social-accounts',
    ),

    'oauth_authorize_url' => 'https://www.instagram.com/oauth/authorize',

    'oauth_token_url' => 'https://api.instagram.com/oauth/access_token',

    'graph_base_url' => 'https://graph.instagram.com',

    'api_version' => env('INSTAGRAM_API_VERSION', 'v21.0'),

    'scopes' => [
        'instagram_business_basic',
        'instagram_business_content_publish',
    ],

    'state_ttl_minutes' => (int) env('INSTAGRAM_STATE_TTL_MINUTES', 15),

    /** Public URL prefix for media files (Instagram must download image_url). */
    'media_public_base_url' => env(
        'INSTAGRAM_MEDIA_PUBLIC_BASE_URL',
        rtrim((string) env('APP_URL', ''), '/').'/storage',
    ),

];
