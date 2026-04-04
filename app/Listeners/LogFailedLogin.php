<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Log;

class LogFailedLogin
{
    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        Log::warning('Login failed', [
            'email' => $event->credentials['email'] ?? null,
            'ip' => request()->ip(),
        ]);
    }
}
