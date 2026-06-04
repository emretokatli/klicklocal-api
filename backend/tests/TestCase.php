<?php

namespace Tests;

use App\Models\Plan;
use App\Models\Workspace;
use App\Services\Billing\WorkspaceSubscriptionService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->seed(RolesAndPermissionsSeeder::class);
            $this->seed(PlanSeeder::class);
        }
    }

    protected function subscribeWorkspace(Workspace $workspace): void
    {
        $plan = Plan::query()->where('slug', 'starter')->first()
            ?? Plan::query()->where('is_active', true)->first();

        if ($plan !== null) {
            app(WorkspaceSubscriptionService::class)->subscribe($workspace, $plan);
        }
    }
}
