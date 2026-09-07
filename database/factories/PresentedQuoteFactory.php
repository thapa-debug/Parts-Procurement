<?php

namespace Database\Factories;

use App\Models\PartRequest;
use App\Models\PresentedQuote;
use App\Models\VendorResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PresentedQuote>
 */
class PresentedQuoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $costPrice = fake()->numberBetween(5_000, 200_000);
        $appliedRate = 20;
        $appliedMinFee = 2_000;
        $margin = max((int) round($costPrice * $appliedRate / 100), $appliedMinFee);

        return [
            'part_request_id' => PartRequest::factory(),
            'vendor_response_id' => VendorResponse::factory(),
            'cost_price' => $costPrice,
            'applied_rate' => $appliedRate,
            'applied_min_fee' => $appliedMinFee,
            'buyer_price' => $costPrice + $margin,
            'presented_at' => now(),
        ];
    }
}
