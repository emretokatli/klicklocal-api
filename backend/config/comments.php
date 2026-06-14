<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI sentiment classification
    |--------------------------------------------------------------------------
    |
    | Comments are classified in batches via one JSON-mode chat completion per
    | batch. `max_per_run` caps how many comments a single ClassifyCommentsJob
    | run may classify (cost guardrail for the hourly scheduler sweep).
    |
    */
    'classification' => [
        'batch_size' => (int) env('COMMENTS_CLASSIFY_BATCH_SIZE', 20),
        'max_per_run' => (int) env('COMMENTS_CLASSIFY_MAX_PER_RUN', 200),
    ],
];
