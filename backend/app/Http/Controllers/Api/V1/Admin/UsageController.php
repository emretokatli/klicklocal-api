<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SubscriptionUsage;
use App\Models\UsageRecord;
use App\Models\Workspace;
use App\Services\Authorization\AuthorizationService;
use App\Services\Billing\FeatureAccessService;
use App\Services\Billing\SubscriptionUsageService;
use App\Support\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly SubscriptionUsageService $subscriptionUsage,
        private readonly FeatureAccessService $features,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorization->assertPlatformPermission(
            $request->user(),
            Permission::VIEW_USAGE_ANALYTICS,
        );

        $validated = $request->validate([
            'workspace_id' => ['nullable', 'integer', 'exists:workspaces,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        if (isset($validated['workspace_id'])) {
            $workspace = Workspace::query()->findOrFail($validated['workspace_id']);

            return ApiResponse::success([
                'workspace_id' => $workspace->id,
                'subscription_usage' => $this->subscriptionUsage->allForWorkspace($workspace),
                'billing_summary' => $this->features->workspaceBillingSummary($workspace),
            ]);
        }

        $records = UsageRecord::query()
            ->with(['user:id,name,email', 'workspace:id,name'])
            ->latest('recorded_at')
            ->limit($validated['limit'] ?? 100)
            ->get();

        $metered = SubscriptionUsage::query()
            ->with('workspace:id,name')
            ->latest('updated_at')
            ->limit($validated['limit'] ?? 100)
            ->get();

        return ApiResponse::success([
            'analytics_records' => $records,
            'subscription_usage' => $metered,
        ]);
    }
}
