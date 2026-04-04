<?php

namespace App\Listeners;

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

        ActivityLogger::info(
            'Two-factor authentication disabled',
            ['ip' => request()->ip()],
            $user->account,
            $user,
        );
    }
}
