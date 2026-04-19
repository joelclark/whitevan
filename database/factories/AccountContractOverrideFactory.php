<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountContractOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountContractOverride>
 */
class AccountContractOverrideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'body' => "# Custom terms\n\nThese are the account-specific terms.",
        ];
    }
}
