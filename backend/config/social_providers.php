<?php

use App\Services\SocialProviders\Fake\FakeFacebookProvider;
use App\Services\SocialProviders\Fake\FakeInstagramProvider;
use App\Services\SocialProviders\Fake\FakeLinkedInProvider;
use App\Services\SocialProviders\Fake\FakeTikTokProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Platform drivers (fake = simulated API, api = real integrations later)
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'facebook' => env('SOCIAL_FACEBOOK_DRIVER', 'fake'),
        'instagram' => env('SOCIAL_INSTAGRAM_DRIVER', 'fake'),
        'linkedin' => env('SOCIAL_LINKEDIN_DRIVER', 'fake'),
        'tiktok' => env('SOCIAL_TIKTOK_DRIVER', 'fake'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider implementations per driver + platform
    |--------------------------------------------------------------------------
    */
    'implementations' => [
        'fake' => [
            'facebook' => FakeFacebookProvider::class,
            'instagram' => FakeInstagramProvider::class,
            'linkedin' => FakeLinkedInProvider::class,
            'tiktok' => FakeTikTokProvider::class,
        ],
        'api' => [
            'instagram' => \App\Services\SocialProviders\Instagram\InstagramProvider::class,
            'facebook' => \App\Services\SocialProviders\Facebook\FacebookProvider::class,
            'tiktok' => \App\Services\SocialProviders\TikTok\TikTokProvider::class,
            // 'linkedin' => \App\Services\SocialProviders\LinkedIn\LinkedInApiProvider::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fake provider simulation settings
    |--------------------------------------------------------------------------
    */
    'fake' => [
        'success_rate' => (float) env('SOCIAL_FAKE_SUCCESS_RATE', 0.85),
        'min_delay_ms' => (int) env('SOCIAL_FAKE_MIN_DELAY_MS', 1000),
        'max_delay_ms' => (int) env('SOCIAL_FAKE_MAX_DELAY_MS', 2000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform capabilities (used for capability checks)
    |--------------------------------------------------------------------------
    */
    'capabilities' => [
        'facebook' => ['publish', 'refresh_token', 'validate_account'],
        'instagram' => ['publish', 'refresh_token', 'validate_account', 'fetch_comments'],
        'linkedin' => ['publish', 'refresh_token', 'validate_account'],
        'tiktok' => ['publish', 'refresh_token', 'validate_account'],
    ],

];
