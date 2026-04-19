<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountContractOverride;
use App\Models\ContractTemplate;

/**
 * Single source of truth for "which contract body applies to this account".
 *
 * Precedence: per-account override (if one exists) → system default. Used by
 * the admin settings page (to show state), the public approval page (to show
 * the effective body to the customer), and the sign action (to source the
 * text that gets snapshotted onto the estimate).
 */
class ContractResolver
{
    public function bodyForAccount(Account $account): string
    {
        $override = $this->overrideFor($account);

        if ($override !== null) {
            return $override->body;
        }

        return $this->default()->body;
    }

    public function overrideFor(Account $account): ?AccountContractOverride
    {
        return AccountContractOverride::withoutGlobalScope('account')
            ->where('account_id', $account->id)
            ->first();
    }

    public function default(): ContractTemplate
    {
        return ContractTemplate::default();
    }
}
