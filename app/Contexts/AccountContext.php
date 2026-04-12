<?php

namespace App\Contexts;

use App\Models\Account;
use App\Models\User;

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

    public function resolveForUser(User $user): ?Account
    {
        $sessionId = session('current_account_id');

        if ($sessionId !== null) {
            $account = $user->accounts()->whereKey($sessionId)->first();
            if ($account !== null) {
                $this->set($account);

                return $account;
            }

            session()->forget('current_account_id');
        }

        $default = $user->defaultAccount();

        if ($default !== null) {
            session(['current_account_id' => $default->id]);
        }

        $this->set($default);

        return $default;
    }

    public function switchTo(User $user, Account $account): Account
    {
        abort_unless($user->isMemberOf($account), 403);
        session(['current_account_id' => $account->id]);
        $this->set($account);

        return $account;
    }
}
