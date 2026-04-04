<?php

namespace App\Listeners;

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

        ActivityLogger::info(
            'Two-factor authentication enabled',
            ['ip' => request()->ip()],
            $user->account,
            $user,
        );
    }
}
