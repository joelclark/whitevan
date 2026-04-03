<?php

use App\Enums\ActivityLogType;
use App\Jobs\WriteActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('failed login records an activity log', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();

    Queue::assertPushed(WriteActivityLog::class, function (WriteActivityLog $job) {
        return $job->description === 'Login failed'
            && $job->type === ActivityLogType::Error;
    });
});
