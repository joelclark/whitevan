<?php

namespace Database\Factories;

use App\Enums\EstimateStatus;
use App\Enums\QuoteStatus;
use App\Enums\Trade;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Project;
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
     * Attach the estimate to a specific project (and therefore account).
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn () => [
            'project_id' => $project->id,
            'account_id' => $project->account_id,
        ]);
    }

    /**
     * Compatibility shim: auto-creates a Project on the customer and
     * attaches the estimate to it. Lets existing callers migrate in
     * lockstep without rewriting every test; safe to inline later.
     */
    public function forCustomer(Customer $customer): static
    {
        $project = Project::factory()->forCustomer($customer)->create();

        return $this->forProject($project);
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

    public function quoteSent(): static
    {
        return $this->state(fn () => [
            'quote_status' => QuoteStatus::Sent,
            'quote_token' => Str::ulid()->toBase32(),
            'quote_sent_at' => now(),
        ]);
    }
}
