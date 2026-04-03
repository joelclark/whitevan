<?php

use App\Models\User;

test('successful login records an activity log', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();

    $this->assertDatabaseHas('activity_logs', [
        'type' => 'info',
        'description' => 'User logged in',
    ]);
});
