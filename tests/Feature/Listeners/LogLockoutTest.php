<?php

use App\Listeners\LogLockout;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

test('lockout listener logs a warning to the application log', function () {
    Log::spy();

    $user = User::factory()->create();
    $request = Request::create('/login', 'POST', [
        'email' => $user->email,
    ]);
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    $listener = new LogLockout;
    $listener->handle(new Lockout($request));

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => $message === 'Login lockout')
        ->once();

    $this->assertDatabaseMissing('activity_logs', [
        'description' => 'Login lockout',
    ]);
});
