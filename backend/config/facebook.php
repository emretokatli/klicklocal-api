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

    'oauth_authorize_url' => 'https://www.facebook.com/'.env('FACEBOOK_API_VERSION', 'v25.0').'/dialog/oauth',

    'graph_base_url' => 'https://graph.facebook.com',

    /*
    | Scopes:
    | - pages_show_list        : list the Pages the user manages
    | - pages_read_engagement  : read Page content/engagement
    | - pages_manage_posts     : create/edit/delete Page feed posts + photos
    | - pages_manage_metadata  : required for video publishing
    | - pages_manage_engagement: required for Reels publishing
    */
    'scopes' => [
        'pages_show_list',
        'pages_read_engagement',
        'pages_manage_posts',
        'pages_manage_metadata',
        'pages_manage_engagement',
    ],

    'state_ttl_minutes' => (int) env('FACEBOOK_STATE_TTL_MINUTES', 15),

    /** Public URL prefix for media files (Facebook must download photo/video by URL). */
    'media_public_base_url' => env(
        'FACEBOOK_MEDIA_PUBLIC_BASE_URL',
        rtrim((string) env('APP_URL', ''), '/').'/storage',
    ),

];
