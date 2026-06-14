<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Website Analyze (Claude Agent SDK + klicklocal-webanalyze skill)
    |--------------------------------------------------------------------------
    |
    | driver: fake       — deterministic placeholder report (default without API key)
    | driver: code_first — collects all data in PHP, then one Anthropic call (default with key)
    | driver: api        — runs backend/agent-sdk/analyze-website.mjs via Node.js (legacy agent)
    |
    */
    'driver' => env('WEBANALYZE_DRIVER', env('ANTHROPIC_API_KEY') ? 'code_first' : 'fake'),
    'api_key' => env('ANTHROPIC_API_KEY', ''),
    'node_binary' => env('WEBANALYZE_NODE_BINARY', 'node'),
    'script_path' => env('WEBANALYZE_SCRIPT_PATH', base_path('agent-sdk/analyze-website.mjs')),
    'project_root' => env('WEBANALYZE_PROJECT_ROOT', dirname(base_path())),
    'timeout' => (int) env('WEBANALYZE_TIMEOUT', 900),
    'max_turns' => (int) env('WEBANALYZE_MAX_TURNS', 20),
    'max_budget_usd' => (float) env('WEBANALYZE_MAX_BUDGET_USD', 1.25),
    'model' => env('WEBANALYZE_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Code-first driver settings
    |--------------------------------------------------------------------------
    |
    | Used when driver === 'code_first'. All website/social data is gathered in
    | PHP; a single Anthropic Messages call synthesises the report.
    |
    */
    'report_model' => env('WEBANALYZE_REPORT_MODEL', 'claude-haiku-4-5-20251001'),
    'serp_api_key' => env('SERP_API_KEY', ''),
    'serp_driver' => env('SERP_DRIVER', env('SERP_API_KEY') ? 'api' : 'fake'),
    'cache_ttl_hours' => (int) env('WEBANALYZE_CACHE_TTL_HOURS', 168),  // 7 days
    'cache_driver' => env('WEBANALYZE_CACHE_DRIVER', 'redis'),          // or 'array' in tests
];
