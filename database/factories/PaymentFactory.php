<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\PartRequest;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'part_request_id' => PartRequest::factory(),
            'amount' => fake()->numberBetween(5_000, 500_000),
            'currency' => 'JPY',
            'status' => PaymentStatus::Pending,
            'gateway' => 'stub',
            'gateway_reference' => null,
            'raw_response' => null,
            'paid_at' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Confirmed,
            'gateway_reference' => 'stub_'.fake()->uuid(),
            'raw_response' => ['stub' => true],
            'paid_at' => now(),
        ]);
    }
}
