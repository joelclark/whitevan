<?php

use App\Enums\ActivityLogType;
use App\Jobs\WriteActivityLog;
use App\Listeners\LogLockout;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;

test('lockout listener records an activity log', function () {
    Queue::fake();

    $user = User::factory()->create();
    $request = Request::create('/login', 'POST', [
        'email' => $user->email,
    ]);
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    $listener = new LogLockout;
    $listener->handle(new Lockout($request));

    Queue::assertPushed(WriteActivityLog::class, function (WriteActivityLog $job) use ($user) {
        return $job->description === 'Login lockout'
            && $job->type === ActivityLogType::Error
            && $job->userId === $user->id;
    });
});
