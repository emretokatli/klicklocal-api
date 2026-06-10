<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return ApiResponse::success(
            $this->billing->overview($workspace),
        );
    }

    public function transactions(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return ApiResponse::success([
            'transactions' => $this->billing->transactions($workspace),
        ]);
    }
}
