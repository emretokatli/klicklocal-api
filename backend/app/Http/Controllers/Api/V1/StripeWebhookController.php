<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing\Stripe\StripeWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeWebhookHandler $handler,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $type = $payload['type'] ?? $request->header('Stripe-Event-Type', 'unknown');

        $this->handler->handle($type, $payload);

        return response()->json(['received' => true]);
    }
}
