<?php

use App\Listeners\LogLockout;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;

test('lockout listener records an activity log', function () {
    $user = User::factory()->create();
    $request = Request::create('/login', 'POST', [
        'email' => $user->email,
    ]);
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    $listener = new LogLockout;
    $listener->handle(new Lockout($request));

    $this->assertDatabaseHas('activity_logs', [
        'type' => 'error',
        'description' => 'Login lockout',
        'user_id' => $user->id,
    ]);
});
