<?php

namespace Database\Factories;

use App\Enums\ActivityEvent;
use App\Enums\ActorType;
use App\Models\Estimate;
use App\Models\Project;
use App\Models\ProjectEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectEvent>
 */
class ProjectEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => null,
            'project_id' => null,
            'estimate_id' => null,
            'event' => ActivityEvent::ProjectCreated,
            'user_id' => null,
            'actor_type' => ActorType::Contractor,
            'customer_visible' => false,
            'metadata' => null,
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn () => [
            'project_id' => $project->id,
            'account_id' => $project->account_id,
        ]);
    }

    public function forEstimate(Estimate $estimate): static
    {
        return $this->state(fn () => [
            'estimate_id' => $estimate->id,
            'project_id' => $estimate->project_id,
            'account_id' => $estimate->account_id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state([
            'user_id' => $user->id,
            'actor_type' => ActorType::Contractor,
        ]);
    }

    public function customer(): static
    {
        return $this->state([
            'user_id' => null,
            'actor_type' => ActorType::Customer,
        ]);
    }

    public function system(): static
    {
        return $this->state([
            'user_id' => null,
            'actor_type' => ActorType::System,
        ]);
    }
}
