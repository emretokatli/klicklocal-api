<?php

return [

    'currency' => env('BILLING_CURRENCY', 'EUR'),

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'default_trial_days' => (int) env('BILLING_DEFAULT_TRIAL_DAYS', 14),

];
