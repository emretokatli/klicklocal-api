<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\QuotaAddon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotaTopupController extends Controller
{
    /** @return array<int, array{key: string, label: string, amount: int, price: float}> */
    private static function packages(): array
    {
        return [
            ['key' => 'ai_monthly_tokens', 'label' => '50 extra KI-Generierungen', 'amount' => 50, 'price' => 9.99],
            ['key' => 'scheduled_posts_monthly', 'label' => '30 extra geplante Posts', 'amount' => 30, 'price' => 4.99],
        ];
    }

    public function listPackages(): JsonResponse
    {
        return ApiResponse::success(['packages' => self::packages()]);
    }

    public function purchase(Request $request): JsonResponse
    {
        $packageKeys = array_column(self::packages(), 'key');

        $validated = $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'package_key' => ['required', 'string', 'in:'.implode(',', $packageKeys)],
        ]);

        $workspace = $request->attributes->get('workspace');

        $package = collect(self::packages())
            ->firstWhere('key', $validated['package_key']);

        $addon = QuotaAddon::create([
            'workspace_id' => $workspace->id,
            'feature_key' => $package['key'],
            'amount' => $package['amount'],
            'expires_at' => now()->endOfMonth(),
            'purchased_at' => now(),
            'price_paid' => $package['price'],
            'provider' => 'manual',
        ]);

        return ApiResponse::success(
            ['addon' => $addon],
            'Top-up purchased.',
            201,
        );
    }
}
