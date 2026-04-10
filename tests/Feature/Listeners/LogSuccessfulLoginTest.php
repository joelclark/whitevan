<?php

use App\Models\ActivityLog;
use App\Models\User;

test('successful login records an activity log', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();

    $log = ActivityLog::where('description', 'User logged in')->first();

    expect($log)->not->toBeNull();
    expect($log->metadata)->toMatchArray(['email' => $user->email]);
});
