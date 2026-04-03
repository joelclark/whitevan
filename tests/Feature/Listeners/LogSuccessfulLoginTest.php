<?php

use App\Enums\ActivityLogType;
use App\Jobs\WriteActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('successful login records an activity log', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();

    Queue::assertPushed(WriteActivityLog::class, function (WriteActivityLog $job) {
        return $job->description === 'User logged in'
            && $job->type === ActivityLogType::Info;
    });
});
