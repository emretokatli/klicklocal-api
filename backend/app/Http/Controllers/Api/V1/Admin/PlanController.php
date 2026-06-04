<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Plan;
use App\Services\Subscription\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function __construct(
        private readonly PlanService $plans,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Plan::class);

        return ApiResponse::success(['plans' => $this->plans->listAll()]);
    }

    public function show(Plan $plan): JsonResponse
    {
        $this->authorize('view', $plan);

        return ApiResponse::success(['plan' => $plan->load('features')]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Plan::class);

        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:64', 'unique:plans,slug'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'yearly_price' => ['nullable', 'numeric', 'min:0'],
            'trial_days' => ['integer', 'min:0', 'max:90'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string'],
        ]);

        $features = $validated['features'] ?? null;
        unset($validated['features']);

        $plan = $this->plans->create($validated, $features);

        return ApiResponse::success(['plan' => $plan], 'Plan created.', 201);
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $this->authorize('update', $plan);

        $validated = $request->validate([
            'slug' => ['sometimes', 'string', 'max:64', 'unique:plans,slug,'.$plan->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'monthly_price' => ['sometimes', 'numeric', 'min:0'],
            'yearly_price' => ['nullable', 'numeric', 'min:0'],
            'trial_days' => ['integer', 'min:0', 'max:90'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
            'features' => ['nullable', 'array'],
        ]);

        $features = $validated['features'] ?? null;
        unset($validated['features']);

        $updated = $this->plans->update($plan, $validated, $features);

        return ApiResponse::success(['plan' => $updated], 'Plan updated.');
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $this->authorize('delete', $plan);

        $this->plans->delete($plan);

        return ApiResponse::success(null, 'Plan deleted.');
    }

    public function featureKeys(): JsonResponse
    {
        return ApiResponse::success([
            'keys' => array_column(PlanFeature::cases(), 'value'),
        ]);
    }
}
