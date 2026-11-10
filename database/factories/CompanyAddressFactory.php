<?php

namespace Database\Factories;

use App\Models\CompanyAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyAddress>
 */
class CompanyAddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->unique()->randomElement(['Head office', 'Chiba yard', 'Saitama warehouse', 'Yokohama depot', 'Osaka branch']).' '.fake()->unique()->numberBetween(1, 9999),
            'recipient_name' => fake()->name(),
            'phone' => fake()->numerify('0#-####-####'),
            'postal_code' => fake()->numerify('###-####'),
            'prefecture' => fake()->randomElement(['Tokyo', 'Chiba', 'Saitama', 'Kanagawa', 'Osaka']),
            'city' => fake()->city(),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => null,
        ];
    }
}
