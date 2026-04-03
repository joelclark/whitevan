<?php

namespace Database\Factories;

use App\Enums\ActivityLogType;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(ActivityLogType::cases()),
            'description' => fake()->sentence(),
            'metadata' => null,
            'account_id' => null,
            'user_id' => null,
        ];
    }

    /**
     * Set the log type to info.
     */
    public function info(): static
    {
        return $this->state(['type' => ActivityLogType::Info]);
    }

    /**
     * Set the log type to error.
     */
    public function error(): static
    {
        return $this->state(['type' => ActivityLogType::Error]);
    }

    /**
     * Associate the log with an account.
     */
    public function forAccount(Account $account): static
    {
        return $this->state(['account_id' => $account->id]);
    }

    /**
     * Associate the log with a user.
     */
    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }
}
