<?php

namespace App\Listeners;

use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        ActivityLogger::info(
            'User logged in',
            ['ip' => request()->ip()],
            $user->account,
            $user,
        );
    }
}
