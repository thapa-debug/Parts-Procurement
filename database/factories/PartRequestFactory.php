<?php

namespace Database\Factories;

use App\Enums\PartType;
use App\Enums\RequestStatus;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartRequest>
 */
class PartRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_id' => BuyerProfile::factory(),
            'request_code' => fake()->unique()->numerify('REQ-######'),
            'part_type' => fake()->randomElement(PartType::cases()),
            'maker' => fake()->randomElement(['Toyota', 'Nissan', 'Honda', 'Mazda', 'Subaru']),
            'car_model' => fake()->bothify('Model-###'),
            'part_name' => fake()->randomElement(['Front bumper', 'Alternator', 'Headlight assembly', 'ECU', 'Side mirror']),
            'status' => RequestStatus::New,
        ];
    }
}
