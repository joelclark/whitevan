<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountDepositOverride;
use App\Models\DepositDefaults;

/**
 * Single source of truth for the deposit percentages that apply to a given
 * account's quotes. Each side (material, labor) resolves independently:
 * override if the admin set one, otherwise the sysop default. A partial
 * override (one percent null, one set) falls back to the default only on
 * the null side.
 */
class DepositResolver
{
    public function materialPercentFor(Account $account): int
    {
        return $this->overrideFor($account)?->material_deposit_percent
            ?? $this->defaults()->material_deposit_percent;
    }

    public function laborPercentFor(Account $account): int
    {
        return $this->overrideFor($account)?->labor_deposit_percent
            ?? $this->defaults()->labor_deposit_percent;
    }

    public function overrideFor(Account $account): ?AccountDepositOverride
    {
        return AccountDepositOverride::withoutGlobalScope('account')
            ->where('account_id', $account->id)
            ->first();
    }

    public function defaults(): DepositDefaults
    {
        return DepositDefaults::default();
    }
}
