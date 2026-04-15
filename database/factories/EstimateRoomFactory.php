<?php

namespace Database\Factories;

use App\Models\EstimateRoom;
use App\Services\LinearFeetFallback;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstimateRoom>
 */
class EstimateRoomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sqft = fake()->numberBetween(60, 400);

        return [
            'name' => fake()->randomElement([
                'Kitchen', 'Living Room', 'Bedroom', 'Bathroom', 'Office', 'Hallway',
            ]),
            'page' => fake()->numberBetween(1, 3),
            'sqft' => $sqft,
            'linear_feet' => LinearFeetFallback::approximate($sqft),
            'position' => 0,
        ];
    }
}
