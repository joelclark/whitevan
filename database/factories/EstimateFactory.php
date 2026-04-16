<?php

namespace Database\Factories;

use App\Enums\EstimateStatus;
use App\Enums\Trade;
use App\Models\Customer;
use App\Models\Estimate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Estimate>
 */
class EstimateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trade' => Trade::Flooring,
            'title' => fake()->randomElement([
                'Main floor',
                'Upstairs bedrooms',
                'Basement finish',
                'Whole house',
            ]).' — '.fake()->lastName(),
            'pdf_path' => 'estimate-pdfs/'.Str::ulid().'.pdf',
            'pdf_original_filename' => 'floor-plan-'.fake()->numberBetween(1, 999).'.pdf',
            'total_sqft' => fake()->numberBetween(200, 4000),
            'status' => EstimateStatus::Ready,
            'interview_answers' => [],
            'agent_errors' => [],
        ];
    }

    /**
     * Attach the estimate to a specific customer (and therefore account).
     */
    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn () => [
            'customer_id' => $customer->id,
            'account_id' => $customer->account_id,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => EstimateStatus::Processing]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => EstimateStatus::Failed,
            'agent_errors' => ['The PDF could not be read.'],
        ]);
    }
}
