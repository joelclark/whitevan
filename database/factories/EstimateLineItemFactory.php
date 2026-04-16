<?php

namespace Database\Factories;

use App\Enums\LineItemCategory;
use App\Enums\LineItemUnit;
use App\Models\EstimateLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstimateLineItem>
 */
class EstimateLineItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(3),
            'label' => fake()->sentence(3),
            'category' => fake()->randomElement(LineItemCategory::cases()),
            'unit' => fake()->randomElement(LineItemUnit::cases()),
            'quantity' => fake()->randomFloat(2, 1, 500),
            'unit_price' => null,
            'notes' => null,
            'position' => 0,
        ];
    }
}
