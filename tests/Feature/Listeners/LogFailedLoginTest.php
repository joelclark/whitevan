<?php

use App\Models\User;
use Illuminate\Support\Facades\Log;

test('failed login logs a warning to the application log', function () {
    Log::spy();

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => $message === 'Login failed')
        ->once();

    $this->assertDatabaseMissing('activity_logs', [
        'description' => 'Login failed',
    ]);
});
