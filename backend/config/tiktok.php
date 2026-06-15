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

    /*
    | Audit gate. Until the TikTok app passes Content Posting API audit, every
    | post MUST be created with privacy SELF_ONLY and branded-content disabled
    | (unaudited clients are sandboxed to private posts). Flip to true only after
    | TikTok approves the app for direct posting.
    */
    'audited' => (bool) env('TIKTOK_AUDITED', false),

    'oauth_authorize_url' => 'https://www.tiktok.com/v2/auth/authorize/',

    'oauth_token_url' => 'https://open.tiktokapis.com/v2/oauth/token/',

    'user_info_url' => 'https://open.tiktokapis.com/v2/user/info/',

    // Content Posting API endpoints
    'creator_info_url' => 'https://open.tiktokapis.com/v2/post/publish/creator_info/query/',

    'video_init_url' => 'https://open.tiktokapis.com/v2/post/publish/video/init/',

    'status_fetch_url' => 'https://open.tiktokapis.com/v2/post/publish/status/fetch/',

    // Display API: list a creator's videos with engagement metrics.
    'video_list_url' => 'https://open.tiktokapis.com/v2/video/list/',

    'scopes' => [
        'user.info.basic',
        'video.publish',
        'video.upload',
    ],

    'state_ttl_minutes' => (int) env('TIKTOK_STATE_TTL_MINUTES', 15),

    /** Public URL prefix for media files (TikTok must download the video via PULL_FROM_URL). */
    'media_public_base_url' => env(
        'TIKTOK_MEDIA_PUBLIC_BASE_URL',
        rtrim((string) env('APP_URL', ''), '/').'/storage',
    ),

    // Status polling for the PULL_FROM_URL publish flow.
    'publish_poll_max_attempts' => (int) env('TIKTOK_PUBLISH_POLL_MAX_ATTEMPTS', 20),

    'publish_poll_interval_seconds' => (int) env('TIKTOK_PUBLISH_POLL_INTERVAL_SECONDS', 3),

];
