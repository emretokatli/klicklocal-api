<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing\RevenueCatWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevenueCatWebhookController extends Controller
{
    public function __construct(
        private readonly RevenueCatWebhookService $handler,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $this->handler->handle($request->all());

        return response()->json(['received' => true]);
    }
}
