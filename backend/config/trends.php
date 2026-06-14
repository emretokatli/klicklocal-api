<?php

use App\Services\Trends\Fake\FakeTrendProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Active trend driver (fake = seeded/placeholder data, api = real later)
    |--------------------------------------------------------------------------
    */
    'driver' => env('TREND_DRIVER', 'fake'),

    /*
    |--------------------------------------------------------------------------
    | Provider implementations per driver
    |--------------------------------------------------------------------------
    |
    | Mirrors config/social_providers.php. Add an 'api' implementation here once
    | a real trend ingestion source (e.g. platform APIs, SerpApi) is built.
    |
    */
    'implementations' => [
        'fake' => FakeTrendProvider::class,
        // 'api' => \App\Services\Trends\Api\ApiTrendProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Driver capabilities (used for capability checks)
    |--------------------------------------------------------------------------
    */
    'capabilities' => [
        'fake' => ['topics', 'hashtags', 'audio', 'formats'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default number of items returned per trend category
    |--------------------------------------------------------------------------
    */
    'default_limit' => (int) env('TREND_DEFAULT_LIMIT', 20),

];
