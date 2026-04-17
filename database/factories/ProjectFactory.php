<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Main floor',
                'Upstairs bedrooms',
                'Basement finish',
                'Whole house',
                'Kitchen remodel',
            ]),
            'site_address_line_1' => null,
            'site_address_line_2' => null,
            'site_city' => null,
            'site_state' => null,
            'site_zip' => null,
            'notes' => null,
        ];
    }

    /**
     * Attach the project to a specific customer (and therefore account).
     */
    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn () => [
            'customer_id' => $customer->id,
            'account_id' => $customer->account_id,
        ]);
    }
}
