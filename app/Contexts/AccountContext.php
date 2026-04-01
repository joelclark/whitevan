<?php

namespace App\Contexts;

use App\Models\Account;

class AccountContext
{
    private ?Account $account = null;

    /**
     * Set the current account for this request.
     */
    public function set(?Account $account): void
    {
        $this->account = $account;
    }

    /**
     * Get the current account, or null if no account context is active.
     */
    public function get(): ?Account
    {
        return $this->account;
    }

    /**
     * Get the current account ID, or null if no account context is active.
     */
    public function id(): ?int
    {
        return $this->account?->id;
    }
}
