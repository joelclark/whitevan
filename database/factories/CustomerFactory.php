<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company' => fake()->optional()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'address_line_1' => fake()->optional()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->optional()->city(),
            'state' => fake()->optional()->stateAbbr(),
            'zip' => fake()->optional()->postcode(),
            'notes' => null,
            'last_accessed_at' => null,
        ];
    }

    /**
     * Mark the customer as having been viewed at a specific time.
     */
    public function viewed(?\DateTimeInterface $at = null): static
    {
        return $this->state(fn () => [
            'last_accessed_at' => $at ?? now(),
        ]);
    }
}
