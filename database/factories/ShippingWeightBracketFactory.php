<?php

namespace Database\Factories;

use App\Models\ShippingWeightBracket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingWeightBracket>
 */
class ShippingWeightBracketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'upper_kg' => fake()->randomFloat(2, 5, 500),
            'fee' => fake()->numberBetween(1_000, 100_000),
            'order' => fake()->unique()->numberBetween(1, 1000),
        ];
    }

    /**
     * The top/catch-all bracket -- "and above", no upper bound.
     */
    public function catchAll(): static
    {
        return $this->state(fn (array $attributes) => ['upper_kg' => null]);
    }
}
