<?php

namespace Database\Factories;

use App\Models\BuyerProfile;
use App\Models\Country;
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
            'country_id' => Country::factory(),
            'phone' => fake()->phoneNumber(),
            // Approved by default -- most tests using this factory exercise
            // something unrelated to the approval gate and shouldn't have to
            // think about it. Use ->pending() for the deliberately-unapproved
            // case (CLAUDE.md §14).
            'approved_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }
}
