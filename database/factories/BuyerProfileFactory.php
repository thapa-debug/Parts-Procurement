<?php

namespace Database\Factories;

use App\Models\BuyerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerProfile>
 */
class BuyerProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->buyer(),
            'company_name' => fake()->company(),
            'member_code' => fake()->unique()->numerify('BYR-######'),
            'default_destination_country' => fake()->country(),
            'default_yard' => fake()->city().' Yard',
            'phone' => fake()->phoneNumber(),
        ];
    }
}
