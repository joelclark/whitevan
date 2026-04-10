<?php

namespace App\Listeners;

use App\Enums\ActivityEvent;
use App\Services\ActivityLogger;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

class LogTwoFactorDisabled
{
    /**
     * Handle the event.
     */
    public function handle(TwoFactorAuthenticationDisabled $event): void
    {
        $user = $event->user;

        ActivityLogger::event(
            ActivityEvent::UserTwoFactorDisabled,
            metadata: ['ip' => request()->ip()],
            account: $user->account,
            user: $user,
        );
    }
}
