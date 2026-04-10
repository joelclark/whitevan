<?php

namespace App\Listeners;

use App\Enums\ActivityEvent;
use App\Services\ActivityLogger;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

class LogTwoFactorEnabled
{
    /**
     * Handle the event.
     */
    public function handle(TwoFactorAuthenticationEnabled $event): void
    {
        $user = $event->user;

        ActivityLogger::event(
            ActivityEvent::UserTwoFactorEnabled,
            metadata: ['ip' => request()->ip()],
            account: $user->account,
            user: $user,
        );
    }
}
