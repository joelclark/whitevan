<?php

namespace Database\Factories;

use App\Models\EstimateFloorplanPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstimateFloorplanPage>
 */
class EstimateFloorplanPageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $page = fake()->numberBetween(1, 5);

        return [
            'page' => $page,
            'image_path' => "estimate-floorplan-pages/0/p{$page}.png",
            'width' => 1275,
            'height' => 1650,
        ];
    }
}
