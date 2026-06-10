<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Billing\WorkspaceSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly WorkspaceSubscriptionService $subscriptions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        return ApiResponse::success([
            'subscriptions' => $this->subscriptions->listAll(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Subscription::class);

        $validated = $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['nullable', 'string', 'in:monthly,yearly'],
        ]);

        $workspace = Workspace::query()->findOrFail($validated['workspace_id']);
        $plan = Plan::query()->findOrFail($validated['plan_id']);

        $subscription = $this->subscriptions->subscribe(
            $workspace,
            $plan,
            $validated['billing_cycle'] ?? 'monthly',
            actor: $request->user(),
        );

        return ApiResponse::success(
            ['subscription' => $subscription],
            'Subscription assigned.',
            201,
        );
    }

    public function grantDemo(Request $request): JsonResponse
    {
        $this->authorize('create', Subscription::class);

        $validated = $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $workspace = Workspace::query()->findOrFail($validated['workspace_id']);

        $subscription = $this->subscriptions->grantDemoPeriod(
            $workspace,
            $validated['days'],
            $request->user(),
        );

        return ApiResponse::success(
            ['subscription' => $subscription],
            'Demo period granted.',
            201,
        );
    }

    public function destroy(Subscription $subscription): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);

        $this->subscriptions->cancel($subscription);

        return ApiResponse::success(null, 'Subscription cancelled.');
    }
}
