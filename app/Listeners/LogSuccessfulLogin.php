<?php

namespace App\Listeners;

use App\Contexts\AccountContext;
use App\Enums\ActivityEvent;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    /**
     * Handle the event.
     */
    /**
     * Skip rewriting last_login_at if it was updated within this many seconds.
     * The Login event also fires on remember-me cookie rehydration, so without
     * a debounce we'd write to the users row on every authenticated page load.
     */
    private const LAST_LOGIN_DEBOUNCE_SECONDS = 60;

    public function handle(Login $event): void
    {
        $user = $event->user;

        ActivityLogger::event(
            ActivityEvent::UserLoggedIn,
            metadata: ['ip' => request()->ip(), 'email' => $user->email],
            account: app(AccountContext::class)->resolveForUser($user),
            user: $user,
        );

        $lastLogin = $user->last_login_at;

        if ($lastLogin === null || $lastLogin->lte(now()->subSeconds(self::LAST_LOGIN_DEBOUNCE_SECONDS))) {
            $user->forceFill(['last_login_at' => now()])->save();
        }
    }
}
