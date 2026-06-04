<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Support\Facades\Cache;

class PlanFeatureService
{
    /**
     * @param  array<string, string|int|bool>  $features
     */
    public function sync(Plan $plan, array $features): void
    {
        foreach ($features as $key => $value) {
            PlanFeature::updateOrCreate(
                ['plan_id' => $plan->id, 'feature_key' => $key],
                ['feature_value' => (string) (is_bool($value) ? ($value ? '1' : '0') : $value)],
            );
        }

        Cache::forget('plan.features.'.$plan->id);
    }
}
