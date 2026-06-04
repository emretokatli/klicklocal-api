<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'Scheduler SaaS API',
        'version' => '1.0.0',
        'documentation' => url('api/documentation'),
    ]);
});

// Do not use /docs — L5-Swagger serves api-docs.json at GET /docs?api-docs.json
Route::redirect('/swagger', '/api/documentation');
