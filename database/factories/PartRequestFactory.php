<?php

namespace Database\Factories;

use App\Enums\PartType;
use App\Enums\RequestStatus;
use App\Models\BuyerProfile;
use App\Models\Maker;
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
            // firstOrCreate rather than depending on MakerSeeder having run
            // first -- draws from the app's full former hardcoded list so
            // generated requests exercise every maker, not an arbitrary subset.
            'maker_id' => Maker::query()->firstOrCreate(['name' => fake()->randomElement([
                'Toyota', 'Nissan', 'Honda', 'Mazda', 'Subaru',
                'Mitsubishi', 'Suzuki', 'Daihatsu', 'Imported / Other',
            ])])->id,
            'car_model' => fake()->bothify('Model-###'),
            'vin' => strtoupper(fake()->bothify('???####-#######')),
            'part_name' => fake()->randomElement(['Front bumper', 'Alternator', 'Headlight assembly', 'ECU', 'Side mirror']),
            'status' => RequestStatus::New,
        ];
    }
}
