<?php

namespace App\Services\Subscription;

use App\Models\Plan;
use App\Services\Billing\PlanFeatureService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PlanService
{
    public function __construct(
        private readonly PlanFeatureService $planFeatures,
    ) {}

    /**
     * @return Collection<int, Plan>
     */
    public function listActive(): Collection
    {
        return Plan::query()
            ->where('is_active', true)
            ->with('features')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return Collection<int, Plan>
     */
    public function listAll(): Collection
    {
        return Plan::query()
            ->with('features')
            ->orderBy('sort_order')
            ->get();
    }

    public function findBySlug(string $slug): ?Plan
    {
        return Plan::query()->where('slug', $slug)->with('features')->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string|int|bool>|null  $features
     */
    public function create(array $data, ?array $features = null): Plan
    {
        $plan = Plan::create($data);

        if ($features !== null) {
            $this->planFeatures->sync($plan, $features);
        }

        return $plan->load('features');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string|int|bool>|null  $features
     */
    public function update(Plan $plan, array $data, ?array $features = null): Plan
    {
        $plan->update($data);

        if ($features !== null) {
            $this->planFeatures->sync($plan, $features);
        }

        Cache::forget('plan.features.'.$plan->id);

        return $plan->fresh(['features']);
    }

    public function delete(Plan $plan): void
    {
        Cache::forget('plan.features.'.$plan->id);
        $plan->delete();
    }
}
