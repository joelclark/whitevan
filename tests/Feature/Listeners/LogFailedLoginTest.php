<?php

use App\Models\User;

test('failed login records an activity log', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();

    $this->assertDatabaseHas('activity_logs', [
        'type' => 'error',
        'description' => 'Login failed',
    ]);
});
