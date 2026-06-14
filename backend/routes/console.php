<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires the scheduler cron on the server:
// * * * * * cd /var/www/klicklocal-api/backend && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('comments:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// Safety net for the event-driven classifier: sweeps comments that are still
// unclassified (e.g. OpenAI was down during ingest). Capped per workspace run
// by comments.classification.max_per_run.
Schedule::command('comments:classify')
    ->hourly()
    ->withoutOverlapping();

// Marks website analyze runs stuck in pending/processing (e.g. worker died
// mid-run) as failed after webanalyze.timeout + 5 min.
Schedule::command('webanalyze:expire-stale')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Intelligence / trend features: register the recurring trend refresh here once
// an 'api' TrendProvider driver and its ingestion command exist. The 'fake'
// driver reads seeded data and needs no schedule. See config/trends.php.
// Schedule::command('trends:refresh')->dailyAt('05:00')->withoutOverlapping();
