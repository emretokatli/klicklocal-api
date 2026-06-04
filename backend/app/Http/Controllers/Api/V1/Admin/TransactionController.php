<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Authorization\AuthorizationService;
use App\Services\Billing\TransactionService;
use App\Support\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly TransactionService $transactions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorization->assertPlatformPermission(
            $request->user(),
            Permission::MANAGE_SUBSCRIPTIONS,
        );

        return ApiResponse::success([
            'transactions' => $this->transactions->listAll(
                (int) $request->query('limit', 100),
            ),
        ]);
    }
}
