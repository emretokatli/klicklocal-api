<?php

namespace App\Services\Billing;

use App\Models\Coupon;
use Illuminate\Support\Collection;

class CouponService
{
    /**
     * @return Collection<int, Coupon>
     */
    public function listAll(): Collection
    {
        return Coupon::query()->latest()->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Coupon
    {
        return Coupon::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Coupon $coupon, array $data): Coupon
    {
        $coupon->update($data);

        return $coupon->fresh();
    }

    public function findByCode(string $code): ?Coupon
    {
        return Coupon::query()->where('code', strtoupper($code))->first();
    }
}
