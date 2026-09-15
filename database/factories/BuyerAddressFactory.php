<?php

namespace Database\Factories;

use App\Models\BuyerAddress;
use App\Models\BuyerProfile;
use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerAddress>
 */
class BuyerAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_id' => BuyerProfile::factory(),
            'recipient_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'postal_code' => fake()->postcode(),
            'country_id' => Country::factory(),
            'state' => fake()->state(),
            'city' => fake()->city(),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => null,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }
}
