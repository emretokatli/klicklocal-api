<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Plan;
use App\Services\Billing\WorkspaceSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly WorkspaceSubscriptionService $subscriptions,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $subscription = $this->subscriptions->activeForWorkspace($workspace);

        return ApiResponse::success([
            'subscription' => $subscription?->load('plan.features'),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['nullable', 'string', 'in:monthly,yearly'],
        ]);

        $plan = Plan::query()->findOrFail($validated['plan_id']);

        $subscription = $this->subscriptions->subscribe(
            $workspace,
            $plan,
            $validated['billing_cycle'] ?? 'monthly',
            actor: $request->user(),
        );

        return ApiResponse::success(
            ['subscription' => $subscription],
            'Subscription started.',
            201,
        );
    }

    public function cancel(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $subscription = $this->subscriptions->activeForWorkspace($workspace);

        if ($subscription === null) {
            return ApiResponse::error('No active subscription.', 404);
        }

        $cancelled = $this->subscriptions->cancel($subscription);

        return ApiResponse::success(
            ['subscription' => $cancelled],
            'Subscription cancelled.',
        );
    }
}
