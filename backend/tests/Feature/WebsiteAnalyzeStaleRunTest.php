<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsiteAnalyzeRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteAnalyzeStaleRunTest extends TestCase
{
    use RefreshDatabase;

    private function makeRun(User $user, string $status, int $ageMinutes, bool $started = true): WebsiteAnalyzeRun
    {
        $run = WebsiteAnalyzeRun::query()->create([
            'user_id' => $user->id,
            'website' => 'https://example.de',
            'status' => $status,
            'started_at' => $started ? now()->subMinutes($ageMinutes) : null,
        ]);

        $run->forceFill(['created_at' => now()->subMinutes($ageMinutes)])->saveQuietly();

        return $run->fresh();
    }

    public function test_stale_pending_and_processing_runs_are_expired(): void
    {
        config(['webanalyze.timeout' => 900]); // grace = 15 min + 5 min = 20 min

        $user = User::factory()->create();

        $staleProcessing = $this->makeRun($user, WebsiteAnalyzeRun::STATUS_PROCESSING, 30);
        $stalePending = $this->makeRun($user, WebsiteAnalyzeRun::STATUS_PENDING, 30, started: false);
        $freshProcessing = $this->makeRun($user, WebsiteAnalyzeRun::STATUS_PROCESSING, 5);
        $oldCompleted = $this->makeRun($user, WebsiteAnalyzeRun::STATUS_COMPLETED, 120);

        $this->artisan('webanalyze:expire-stale')->assertSuccessful();

        $staleProcessing->refresh();
        $this->assertSame(WebsiteAnalyzeRun::STATUS_FAILED, $staleProcessing->status);
        $this->assertSame('Zeitüberschreitung', $staleProcessing->error_message);

        $stalePending->refresh();
        $this->assertSame(WebsiteAnalyzeRun::STATUS_FAILED, $stalePending->status);
        $this->assertSame('Zeitüberschreitung', $stalePending->error_message);

        $this->assertSame(WebsiteAnalyzeRun::STATUS_PROCESSING, $freshProcessing->fresh()->status);
        $this->assertSame(WebsiteAnalyzeRun::STATUS_COMPLETED, $oldCompleted->fresh()->status);
    }

    public function test_processing_run_started_recently_is_kept_even_if_created_long_ago(): void
    {
        config(['webanalyze.timeout' => 900]);

        $user = User::factory()->create();

        // queued for a long time, but the worker only just picked it up
        $run = WebsiteAnalyzeRun::query()->create([
            'user_id' => $user->id,
            'website' => 'https://example.de',
            'status' => WebsiteAnalyzeRun::STATUS_PROCESSING,
            'started_at' => now()->subMinutes(2),
        ]);
        $run->forceFill(['created_at' => now()->subHours(2)])->saveQuietly();

        $this->artisan('webanalyze:expire-stale')->assertSuccessful();

        $this->assertSame(WebsiteAnalyzeRun::STATUS_PROCESSING, $run->fresh()->status);
    }
}
