<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Coupon;
use App\Services\Billing\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function __construct(
        private readonly CouponService $coupons,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Coupon::class);

        return ApiResponse::success(['coupons' => $this->coupons->listAll()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Coupon::class);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:coupons,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'numeric', 'min:0'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after:valid_from'],
            'is_active' => ['boolean'],
        ]);

        $validated['code'] = strtoupper($validated['code']);

        $coupon = $this->coupons->create($validated);

        return ApiResponse::success(['coupon' => $coupon], 'Coupon created.', 201);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $this->authorize('update', $coupon);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(CouponType::class)],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        $updated = $this->coupons->update($coupon, $validated);

        return ApiResponse::success(['coupon' => $updated], 'Coupon updated.');
    }
}
