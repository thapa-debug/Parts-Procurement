<?php

namespace Database\Factories;

use App\Models\ResponsePhoto;
use App\Models\VendorResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResponsePhoto>
 */
class ResponsePhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_response_id' => VendorResponse::factory(),
            'disk' => 's3',
            'path' => 'response-photos/'.fake()->uuid().'.jpg',
            'original_name' => fake()->word().'.jpg',
            'size' => fake()->numberBetween(10_000, 2_000_000),
        ];
    }
}
