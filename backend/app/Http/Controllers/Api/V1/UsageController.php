<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return ApiResponse::success([
            'usage' => $this->billing->usage($workspace),
        ]);
    }
}
