<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PlanFeature;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\QuotaAddon;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuotaAddonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = QuotaAddon::query()->with('workspace:id,name');

        if ($request->filled('workspace_id')) {
            $query->where('workspace_id', (int) $request->query('workspace_id'));
        }

        $addons = $query->latest()->get();

        return ApiResponse::success(['addons' => $addons]);
    }

    public function store(Request $request): JsonResponse
    {
        $featureValues = array_column(PlanFeature::cases(), 'value');

        $validated = $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'feature_key' => ['required', 'string', Rule::in($featureValues)],
            'amount' => ['required', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'price_paid' => ['required', 'numeric', 'min:0'],
        ]);

        $workspace = Workspace::query()->findOrFail($validated['workspace_id']);

        $addon = QuotaAddon::create([
            'workspace_id' => $workspace->id,
            'feature_key' => $validated['feature_key'],
            'amount' => $validated['amount'],
            'expires_at' => $validated['expires_at'] ?? null,
            'purchased_at' => now(),
            'price_paid' => $validated['price_paid'],
            'provider' => 'manual',
        ]);

        return ApiResponse::success(
            ['addon' => $addon->load('workspace:id,name')],
            'Quota add-on assigned.',
            201,
        );
    }
}
