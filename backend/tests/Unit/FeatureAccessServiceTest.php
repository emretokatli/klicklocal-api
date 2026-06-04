<?php

namespace Tests\Unit;

use App\Enums\PlanFeature;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\FeatureAccessService;
use App\Services\Billing\PlanFeatureService;
use App\Services\Billing\WorkspaceSubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_can_use_feature_within_limit(): void
    {
        $this->seed(PlanSeeder::class);

        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Billing WS',
            'slug' => 'billing-ws',
        ]);

        $plan = Plan::query()->where('slug', 'starter')->firstOrFail();
        app(WorkspaceSubscriptionService::class)->subscribe($workspace, $plan);

        $features = app(FeatureAccessService::class);

        $this->assertTrue($features->canUseFeature($workspace, PlanFeature::AiGeneration));
        $this->assertTrue($features->canUseFeature($workspace, PlanFeature::ScheduledPostsMonthly));

        $features->incrementUsage($workspace, PlanFeature::ScheduledPostsMonthly);
        $this->assertSame(1, $features->getUsage($workspace, PlanFeature::ScheduledPostsMonthly));
    }
}
