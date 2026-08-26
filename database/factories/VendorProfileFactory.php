<?php

namespace Database\Factories;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorProfile>
 */
class VendorProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->vendor(),
            'company_name' => fake()->company(),
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'notify_email' => fake()->unique()->safeEmail(),
            'status' => VendorStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => VendorStatus::Suspended]);
    }
}
