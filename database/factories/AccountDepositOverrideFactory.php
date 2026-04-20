<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountDepositOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountDepositOverride>
 */
class AccountDepositOverrideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'material_deposit_percent' => 100,
            'labor_deposit_percent' => 80,
        ];
    }
}
