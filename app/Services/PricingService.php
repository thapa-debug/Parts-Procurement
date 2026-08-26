<?php

namespace App\Services;

use App\Models\Setting;

class PricingService
{
    /**
     * Calculate the buyer-facing price for a vendor's cost price.
     *
     * margin = max(round(cost_price * rate / 100), minFee)
     *
     * @return array{cost_price: int, applied_rate: int, applied_min_fee: int, margin: int, buyer_price: int}
     */
    public function calculate(int $costPrice): array
    {
        $rate = (int) Setting::get('margin_rate', 20);
        $minFee = (int) Setting::get('margin_min_fee', 2000);

        $percentageMargin = (int) round($costPrice * $rate / 100);
        $margin = max($percentageMargin, $minFee);

        return [
            'cost_price' => $costPrice,
            'applied_rate' => $rate,
            'applied_min_fee' => $minFee,
            'margin' => $margin,
            'buyer_price' => $costPrice + $margin,
        ];
    }
}
