<?php

namespace App\Listeners;

use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;
        $user = $event->user;

        ActivityLogger::error(
            'Login failed',
            ['email' => $email, 'ip' => request()->ip()],
            $user?->account,
            $user,
        );
    }
}
