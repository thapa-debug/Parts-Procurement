<?php

namespace Database\Factories;

use App\Enums\LeadTime;
use App\Enums\QualityRank;
use App\Models\PartRequest;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorResponse>
 */
class VendorResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'part_request_id' => PartRequest::factory(),
            'vendor_id' => VendorProfile::factory(),
            'cost_price' => fake()->numberBetween(5_000, 200_000),
            'quality_rank' => fake()->randomElement(QualityRank::cases()),
            'lead_time' => fake()->randomElement(LeadTime::cases()),
            'comment' => fake()->sentence(),
            'is_no_stock' => false,
        ];
    }

    public function noStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost_price' => null,
            'quality_rank' => null,
            'lead_time' => null,
            'is_no_stock' => true,
        ]);
    }
}
