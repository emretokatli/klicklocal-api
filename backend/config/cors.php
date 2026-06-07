<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_merge(
        [
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'http://localhost:1981',
            'http://127.0.0.1:1981',
        ],
        env('CORS_ALLOWED_ORIGINS')
            ? array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS')))
            : [],
    ))),

    'allowed_origins_patterns' => [
        '#^https?://localhost(:\d+)?$#',
        '#^https?://127\.0\.0\.1(:\d+)?$#',
        '#^https://[a-z0-9-]+\.vercel\.app$#',
        // Production + staging subdomains (klicklocal.app, admin.klicklocal.app,
        // test.klicklocal.app, admin-test.klicklocal.app, ...)
        '#^https://([a-z0-9-]+\.)?klicklocal\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
