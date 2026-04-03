<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Lockout;

class LogLockout
{
    /**
     * Handle the event.
     */
    public function handle(Lockout $event): void
    {
        $email = $event->request->input('email');
        $user = User::where('email', $email)->first();

        ActivityLogger::error(
            'Login lockout',
            ['email' => $email, 'ip' => $event->request->ip()],
            $user?->account,
            $user,
        );
    }
}
