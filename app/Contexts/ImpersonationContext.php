<?php

namespace App\Contexts;

use App\Models\Account;

class ImpersonationContext
{
    private ?Account $account = null;

    public function start(Account $account): void
    {
        $this->account = $account;
    }

    public function stop(): void
    {
        $this->account = null;
    }

    public function account(): ?Account
    {
        return $this->account;
    }

    public function isImpersonating(): bool
    {
        return $this->account !== null;
    }
}
