<?php

namespace Database\Seeders;

use App\Enums\PlanFeature;
use App\Models\Plan;
use App\Services\Billing\PlanFeatureService;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $featureService = app(PlanFeatureService::class);

        $plans = [
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'description' => 'For individuals getting started with scheduling.',
                'monthly_price' => 19.99,
                'yearly_price' => 199.00,
                'trial_days' => 14,
                'sort_order' => 0,
                'features' => [
                    PlanFeature::MaxWorkspaces->value => '1',
                    PlanFeature::MaxSocialAccounts->value => '3',
                    PlanFeature::MaxTeamMembers->value => '2',
                    PlanFeature::AiGeneration->value => '1',
                    PlanFeature::AiMonthlyTokens->value => '10000',
                    PlanFeature::AnalyticsEnabled->value => '0',
                    PlanFeature::VideoGeneration->value => '0',
                    PlanFeature::StorageLimitMb->value => '512',
                    PlanFeature::ScheduledPostsMonthly->value => '50',
                    PlanFeature::MediaUploadsMonthly->value => '100',
                    PlanFeature::ApiCallsMonthly->value => '1000',
                ],
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro Mode',
                'description' => 'For small businesses scheduling on Instagram and TikTok.',
                'monthly_price' => 39.99,
                'yearly_price' => 399.00,
                'trial_days' => 14,
                'sort_order' => 1,
                'features' => [
                    PlanFeature::MaxWorkspaces->value => '3',
                    PlanFeature::MaxSocialAccounts->value => '10',
                    PlanFeature::MaxTeamMembers->value => '5',
                    PlanFeature::AiGeneration->value => '1',
                    PlanFeature::AiMonthlyTokens->value => '50000',
                    PlanFeature::AnalyticsEnabled->value => '0',
                    PlanFeature::VideoGeneration->value => '0',
                    PlanFeature::StorageLimitMb->value => '2048',
                    PlanFeature::ScheduledPostsMonthly->value => '120',
                    PlanFeature::MediaUploadsMonthly->value => '500',
                    PlanFeature::ApiCallsMonthly->value => '5000',
                ],
            ],
            [
                'slug' => 'agency',
                'name' => 'Agency Mode',
                'description' => 'Unlimited content and advanced analytics.',
                'monthly_price' => 69.99,
                'yearly_price' => 699.00,
                'trial_days' => 14,
                'sort_order' => 2,
                'features' => [
                    PlanFeature::MaxWorkspaces->value => '10',
                    PlanFeature::MaxSocialAccounts->value => '50',
                    PlanFeature::MaxTeamMembers->value => '25',
                    PlanFeature::AiGeneration->value => '1',
                    PlanFeature::AiMonthlyTokens->value => '200000',
                    PlanFeature::AnalyticsEnabled->value => '1',
                    PlanFeature::VideoGeneration->value => '1',
                    PlanFeature::StorageLimitMb->value => '51200',
                    PlanFeature::ScheduledPostsMonthly->value => '-1',
                    PlanFeature::MediaUploadsMonthly->value => '-1',
                    PlanFeature::ApiCallsMonthly->value => '50000',
                ],
            ],
        ];

        foreach ($plans as $data) {
            $features = $data['features'];
            unset($data['features']);

            $plan = Plan::updateOrCreate(['slug' => $data['slug']], $data);
            $featureService->sync($plan, $features);
        }
    }
}
