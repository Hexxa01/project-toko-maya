<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ShippingService
{
    public function quote(string $courier, string $zone, int $grams): int
    {
        $rate = config('shop.shipping_rates')[$courier] ?? null;
        $area = config('shop.shipping_zones')[$zone] ?? null;
        if (! $rate || ! $area || $grams < 1) {
            throw ValidationException::withMessages(['courier' => 'Kurir atau wilayah pengiriman tidak tersedia.']);
        }

        return (int) ceil((int) $rate * $area['multiplier']) * (int) ceil($grams / 1000);
    }
}
